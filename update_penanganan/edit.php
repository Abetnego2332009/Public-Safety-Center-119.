<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$user_id = $_SESSION['id'];
$id_kasus = (int) ($_GET['id'] ?? 0);

// Validasi akses menggunakan fungsi baru
if (!canUserEditKasus($koneksi, $user_id, $id_kasus)) {
    $_SESSION['error'] = 'Anda tidak memiliki akses untuk mengedit kasus ini.';
    header('Location: index.php');
    exit;
}

if ($id_kasus <= 0) {
    $_SESSION['error'] = 'ID kasus tidak valid.';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, "
    SELECT k.id, k.nomor_kasus, k.status_terakhir, k.tim_id, k.tim_penanggung_jawab_id,
           k.nama_pelapor, k.lokasi_kejadian, k.deskripsi,
           t.label AS triase_label, t.warna_hex
    FROM kasus k
    JOIN triase t ON t.id = k.triase_id
    WHERE k.id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $id_kasus);
mysqli_stmt_execute($stmt);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$kasus) {
    $_SESSION['error'] = 'Kasus tidak ditemukan.';
    header('Location: index.php');
    exit;
}
if (in_array($kasus['status_terakhir'], ['selesai', 'dibatalkan'])) {
    $_SESSION['error'] = 'Kasus sudah selesai/dibatalkan.';
    header('Location: index.php');
    exit;
}

// Status yang tersedia
function status_tersedia($current)
{
    $urutan = [
        'menunggu_respon'      => 0,
        'menuju_lokasi'        => 1,
        'tiba_di_lokasi'       => 2,
        'penanganan_di_tempat' => 3,
        'dirujuk_ke_rs'        => 4,
        'selesai'              => 5,
        'dibatalkan'           => 99
    ];
    $current_level = $urutan[$current] ?? 0;
    $list = [];
    foreach ($urutan as $status => $level) {
        if ($level > $current_level && $status != 'dibatalkan') $list[] = $status;
    }
    if ($current_level < 99) $list[] = 'dibatalkan';
    return $list;
}
$opsi_status = status_tersedia($kasus['status_terakhir']);

$daftar_tim = mysqli_fetch_all(mysqli_query($koneksi, "SELECT id, kode_tim, nama_tim FROM tim WHERE is_aktif = 1"), MYSQLI_ASSOC);

// Cek apakah user adalah tim penanggung jawab aktif (untuk menampilkan form takeover)
$tim_user = null;
$sql_tim = "SELECT tim_id FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL LIMIT 1";
$stmt_tim = mysqli_prepare($koneksi, $sql_tim);
mysqli_stmt_bind_param($stmt_tim, 'i', $user_id);
mysqli_stmt_execute($stmt_tim);
$res_tim = mysqli_stmt_get_result($stmt_tim);
$row_tim = mysqli_fetch_assoc($res_tim);
mysqli_stmt_close($stmt_tim);
if ($row_tim) $tim_user = $row_tim['tim_id'];

$title = 'Update Penanganan - ' . $kasus['nomor_kasus'];
require '../template/header.php';
require '../template/sidebar.php';
?>

<style>
    /* Fix sidebar tidak tertutup konten - asumsi sidebar fixed left */
    .main-content {
        margin-left: 260px;
        /* Sesuaikan dengan lebar sidebar, jika berbeda ganti */
        width: auto;
        position: relative;
        z-index: 1;
    }

    /* Jika sidebar menggunakan posisi fixed, pastikan konten tidak overlap */
    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
        }
    }

    /* Modern minimalis styling */
    :root {
        --card-radius: 1rem;
        --transition: all 0.2s ease;
    }

    .modern-card {
        border: none;
        border-radius: var(--card-radius);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        transition: var(--transition);
    }

    .modern-card .card-header {
        background: transparent;
        border-bottom: 1px solid #eef2f6;
        padding: 1.2rem 1.5rem 0.8rem;
        font-weight: 600;
    }

    .modern-card .card-body {
        padding: 1.5rem;
    }

    /* Info grid minimalis */
    .info-minimal {
        background: #f8fafc;
        border-radius: 0.75rem;
        padding: 1rem 1.2rem;
        margin-bottom: 1.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.8rem 1.5rem;
        border: 1px solid #eef2f6;
    }

    .info-minimal-item {
        flex: 1 1 180px;
    }

    .info-minimal-label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #6c757d;
        font-weight: 600;
    }

    .info-minimal-value {
        font-size: 0.9rem;
        font-weight: 500;
        color: #1e2a3a;
        margin-top: 0.2rem;
    }

    /* Status radio modern (tidak norak) */
    .status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 0.7rem;
        margin-top: 0.5rem;
    }

    .status-radio-btn {
        position: relative;
    }

    .status-radio-btn input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .status-radio-btn label {
        display: block;
        padding: 0.6rem 0.5rem;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 2rem;
        font-size: 0.8rem;
        font-weight: 500;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
        color: #2c3e50;
    }

    .status-radio-btn input:checked+label {
        background: #f0f4fe;
        border-color: #5a7c9e;
        color: #1e4663;
        font-weight: 600;
    }

    .status-radio-btn label:hover {
        border-color: #b9c8e0;
        background: #fafcff;
    }

    /* Form styling */
    .form-minimal .form-group {
        margin-bottom: 1.2rem;
    }

    .form-minimal label {
        font-weight: 600;
        font-size: 0.85rem;
        margin-bottom: 0.4rem;
        color: #2c3e50;
    }

    .form-minimal .form-control,
    .form-minimal select.form-control {
        border-radius: 0.65rem;
        border: 1px solid #e2e8f0;
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        transition: var(--transition);
    }

    .form-minimal .form-control:focus,
    .form-minimal select.form-control:focus {
        border-color: #8ba0bc;
        box-shadow: 0 0 0 3px rgba(79, 111, 143, 0.1);
        outline: none;
    }

    /* Tombol minimalis */
    .btn-minimal {
        border-radius: 2rem;
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        transition: var(--transition);
    }

    .btn-save {
        background: #2c5a6e;
        border: none;
        color: white;
    }

    .btn-save:hover {
        background: #1e4050;
        transform: translateY(-1px);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    }

    .btn-cancel {
        background: #f1f5f9;
        border: 1px solid #dee4ec;
        color: #2c3e50;
    }

    .btn-cancel:hover {
        background: #e6edf4;
    }

    /* Divider */
    .hr-minimal {
        margin: 1.2rem 0;
        border-top: 1px solid #edf2f7;
    }

    /* Triase badge kecil */
    .triase-badge-mini {
        display: inline-block;
        background-color: <?= $kasus['warna_hex'] ?>;
        color: white;
        border-radius: 1rem;
        padding: 0.2rem 0.7rem;
        font-size: 0.7rem;
        font-weight: 600;
    }

    /* File input hint */
    .file-hint {
        font-size: 0.65rem;
        color: #8ba0bc;
        margin-top: 0.2rem;
    }

    /* Custom file input styling */
    .custom-file-label:after {
        content: "Browse";
    }

    .custom-file-input:focus~.custom-file-label {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
    }
</style>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
    <div class="header bg-gradient-danger pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-8 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0">Update Penanganan — <?= htmlspecialchars($kasus['nomor_kasus']) ?></h6>
                    </div>
                    <div class="col-lg-4 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral shadow rounded-pill px-3">← Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid mt--7">
        <div class="row">
            <div class="col-xl-8 mx-auto mb-5">
                <div class="card shadow modern-card">
                    <div class="card-header">
                        <h3 class="mb-0" style="font-weight: 600;">Form Update Penanganan</h3>
                        <small class="text-muted">Status saat ini: <strong class="badge badge-pill badge-light px-3 py-1"><?= ucwords(str_replace('_', ' ', $kasus['status_terakhir'])) ?></strong></small>
                    </div>
                    <div class="card-body">
                        <!-- Informasi kasus ringkas, minimalis -->
                        <div class="info-minimal">
                            <div class="info-minimal-item">
                                <div class="info-minimal-label">Pelapor</div>
                                <div class="info-minimal-value"><?= htmlspecialchars($kasus['nama_pelapor']) ?></div>
                            </div>
                            <div class="info-minimal-item">
                                <div class="info-minimal-label">Lokasi</div>
                                <div class="info-minimal-value"><?= htmlspecialchars($kasus['lokasi_kejadian']) ?></div>
                            </div>
                            <div class="info-minimal-item">
                                <div class="info-minimal-label">Triase</div>
                                <div class="info-minimal-value"><span class="triase-badge-mini"><?= $kasus['triase_label'] ?></span></div>
                            </div>
                            <div class="info-minimal-item">
                                <div class="info-minimal-label">Deskripsi</div>
                                <div class="info-minimal-value"><?= nl2br(htmlspecialchars($kasus['deskripsi'])) ?></div>
                            </div>
                        </div>

                        <form action="proses.php" method="POST" enctype="multipart/form-data" class="form-minimal">
                            <input type="hidden" name="id_kasus" value="<?= $id_kasus ?>">

                            <!-- Pilih status baru -->
                            <div class="form-group">
                                <label>Ubah Status Ke <span class="text-danger">*</span></label>
                                <div class="status-grid">
                                    <?php foreach ($opsi_status as $st) :
                                        $display = ucwords(str_replace('_', ' ', $st));
                                    ?>
                                        <div class="status-radio-btn">
                                            <input type="radio" name="status_baru" id="status_<?= $st ?>" value="<?= $st ?>">
                                            <label for="status_<?= $st ?>"><?= $display ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Tim (jika menuju lokasi) -->
                            <div id="div_tim" style="display:none;">
                                <div class="form-group">
                                    <label>Pilih Tim yang Ditugaskan <span class="text-danger">*</span></label>
                                    <select name="id_tim" class="form-control">
                                        <option value="">-- Pilih Tim --</option>
                                        <?php foreach ($daftar_tim as $t): ?>
                                            <option value="<?= $t['id'] ?>" <?= ($kasus['tim_id'] == $t['id']) ? 'selected' : '' ?>><?= $t['nama_tim'] ?> (<?= $t['kode_tim'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Upload foto dengan preview -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Foto Pasien <span class="text-muted">(opsional)</span></label>
                                        <div class="custom-file mb-2">
                                            <input type="file" class="custom-file-input" id="fotoPasienInput" name="foto_pasien" accept="image/jpeg,image/png,image/jpg">
                                            <label class="custom-file-label" for="fotoPasienInput" id="labelFotoPasien">Pilih file...</label>
                                        </div>
                                        <small class="text-muted">Maks 2MB (JPG/PNG)</small>
                                        <div class="mt-2" id="previewPasienArea" style="display:none;">
                                            <img id="previewPasienImg" src="#" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                            <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="clearPasienBtn">Hapus</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-control-label">Foto KTP <span class="text-muted">(opsional)</span></label>
                                        <div class="custom-file mb-2">
                                            <input type="file" class="custom-file-input" id="fotoKTPInput" name="foto_ktp" accept="image/jpeg,image/png,image/jpg">
                                            <label class="custom-file-label" for="fotoKTPInput" id="labelFotoKTP">Pilih file...</label>
                                        </div>
                                        <small class="text-muted">Maks 2MB (JPG/PNG)</small>
                                        <div class="mt-2" id="previewKTRArea" style="display:none;">
                                            <img id="previewKTPImg" src="#" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                                            <button type="button" class="btn btn-sm btn-outline-danger ml-2" id="clearKTPBtn">Hapus</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Rujukan RS -->
                            <div id="div_rs" style="display:none;">
                                <div class="form-group">
                                    <label>Rumah Sakit Tujuan <span class="text-danger">*</span></label>
                                    <select name="rumah_sakit_id" class="form-control" id="select_rs">
                                        <option value="">-- Pilih RS --</option>
                                        <?php
                                        $query_rs = mysqli_query($koneksi, "SELECT id, nama FROM rumah_sakit WHERE is_aktif = 1 ORDER BY nama");
                                        while ($rs = mysqli_fetch_assoc($query_rs)) {
                                            echo '<option value="' . $rs['id'] . '">' . htmlspecialchars($rs['nama']) . '</option>';
                                        }
                                        ?>
                                        <option value="other">[Lainnya] Isi manual</option>
                                    </select>
                                    <input type="text" name="rs_tujuan_manual" class="form-control mt-2" placeholder="Nama RS (isi jika memilih Lainnya)" style="display:none;">
                                    <input type="text" name="rs_lokasi_manual" class="form-control mt-2" placeholder="Lokasi RS (isi jika memilih Lainnya)" style="display:none;">
                                </div>
                            </div>

                            <!-- Catatan -->
                            <div class="form-group">
                                <label>Catatan / Keterangan</label>
                                <textarea name="catatan" rows="2" class="form-control" placeholder="Catatan medis, alasan pembatalan, dll."></textarea>
                                <small class="text-danger" id="catatan_warning" style="display:none;">Alasan pembatalan wajib diisi.</small>
                            </div>

                            <div class="hr-minimal"></div>

                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-cancel btn-minimal">Batal</a>
                                <button type="submit" class="btn btn-save btn-minimal" id="btnSave">Simpan Perubahan</button>
                            </div>
                        </form>

                        <!-- ======== FORM TAKEOVER (ALIHKAN KASUS) ======== -->
                        <?php
                        // Tampilkan form takeover hanya jika user adalah tim penanggung jawab aktif
                        if ($tim_user && $kasus['tim_penanggung_jawab_id'] == $tim_user) {
                            $tim_shift_aktif = getTimBertugasSekarang($koneksi);
                            if ($tim_shift_aktif && $tim_shift_aktif['id'] != $tim_user) {
                        ?>
                                <div class="hr-minimal"></div>
                                <div class="card mt-3 border-warning">
                                    <div class="card-header bg-warning text-white">
                                        <i class="fas fa-exchange-alt"></i> Alihkan Kasus ke Tim Lain
                                    </div>
                                    <div class="card-body">
                                        <p>Anda dapat mengalihkan penanganan kasus ini ke tim yang sedang bertugas sekarang:
                                            <strong><?= htmlspecialchars($tim_shift_aktif['nama_tim']) ?></strong>
                                        </p>
                                        <form method="POST" action="takeover_proses.php">
                                            <input type="hidden" name="kasus_id" value="<?= $id_kasus ?>">
                                            <input type="hidden" name="tim_tujuan" value="<?= $tim_shift_aktif['id'] ?>">
                                            <div class="form-group">
                                                <label>Alasan pengalihan <span class="text-danger">*</span></label>
                                                <textarea name="alasan" class="form-control" rows="2" required placeholder="Contoh: Shift tim saya sudah berakhir, saya serahkan ke tim berikutnya..."></textarea>
                                            </div>
                                            <button type="submit" class="btn btn-warning">Alihkan Kasus</button>
                                        </form>
                                    </div>
                                </div>
                        <?php
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(function() {
        function toggleFields() {
            var st = $('input[name="status_baru"]:checked').val();
            if (st === 'menuju_lokasi') $('#div_tim').slideDown(200);
            else $('#div_tim').slideUp(150);
            if (st === 'dirujuk_ke_rs') $('#div_rs').slideDown(200);
            else $('#div_rs').slideUp(150);
            if (st === 'dibatalkan') {
                $('#catatan_warning').show();
                $('textarea[name="catatan"]').prop('required', true);
            } else {
                $('#catatan_warning').hide();
                $('textarea[name="catatan"]').prop('required', false);
            }
        }
        $('input[name="status_baru"]').on('change', toggleFields);
        if ($('input[name="status_baru"]:checked').length) toggleFields();

        $('#select_rs').on('change', function() {
            if ($(this).val() === 'other') {
                $('input[name="rs_tujuan_manual"]').show();
                $('input[name="rs_lokasi_manual"]').show();
            } else {
                $('input[name="rs_tujuan_manual"]').hide();
                $('input[name="rs_lokasi_manual"]').hide();
            }
        });
    });

    // ============================================
// PREVIEW FOTO PASIEN & KTP
// ============================================
$(function() {
    // Foto Pasien
    const pasienInput = $('#fotoPasienInput');
    const pasienLabel = $('#labelFotoPasien');
    const pasienPreviewArea = $('#previewPasienArea');
    const pasienPreviewImg = $('#previewPasienImg');
    const clearPasienBtn = $('#clearPasienBtn');

    pasienInput.on('change', function(e) {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            pasienLabel.text(file.name);
            const reader = new FileReader();
            reader.onload = function(ev) {
                pasienPreviewImg.attr('src', ev.target.result);
                pasienPreviewArea.show();
            };
            reader.readAsDataURL(file);
        } else {
            pasienLabel.text('Pilih file...');
            pasienPreviewArea.hide();
            pasienPreviewImg.attr('src', '#');
        }
    });

    clearPasienBtn.on('click', function() {
        pasienInput.val('');
        pasienLabel.text('Pilih file...');
        pasienPreviewArea.hide();
        pasienPreviewImg.attr('src', '#');
    });

    // Foto KTP
    const ktpInput = $('#fotoKTPInput');
    const ktpLabel = $('#labelFotoKTP');
    const ktpPreviewArea = $('#previewKTRArea');
    const ktpPreviewImg = $('#previewKTPImg');
    const clearKTPBtn = $('#clearKTPBtn');

    ktpInput.on('change', function(e) {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            ktpLabel.text(file.name);
            const reader = new FileReader();
            reader.onload = function(ev) {
                ktpPreviewImg.attr('src', ev.target.result);
                ktpPreviewArea.show();
            };
            reader.readAsDataURL(file);
        } else {
            ktpLabel.text('Pilih file...');
            ktpPreviewArea.hide();
            ktpPreviewImg.attr('src', '#');
        }
    });

    clearKTPBtn.on('click', function() {
        ktpInput.val('');
        ktpLabel.text('Pilih file...');
        ktpPreviewArea.hide();
        ktpPreviewImg.attr('src', '#');
    });
});
</script>
<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
$nama_role = $_SESSION['nama_role'] ?? '';
if (!in_array($nama_role, ['admin', 'koordinator'])) {
    header('Location: ../dashboard.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$title = 'Tambah Pengguna';
require '../template/header.php';
require '../template/sidebar.php';

// Ambil daftar role
$res_role = mysqli_query($koneksi, "SELECT * FROM roles ORDER BY id");
$roles = mysqli_fetch_all($res_role, MYSQLI_ASSOC);

// Ambil daftar tim aktif untuk keperluan assign anggota tim
$res_tim = mysqli_query($koneksi, "SELECT id, nama_tim FROM tim WHERE is_aktif = 1 ORDER BY nama_tim");
$tim_list = mysqli_fetch_all($res_tim, MYSQLI_ASSOC);
?>

<div class="main-content">
    <?php require '../template/navbar.php'; ?>
   <div class="header bg-gradient-success pb-8 pt-5 pt-md-8">
        <div class="container-fluid">
            <div class="header-body">
                <div class="row align-items-center py-4">
                    <div class="col-lg-6 col-7">
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-user-plus mr-2"></i> Tambah Pengguna</h6>
                    </div>
                    <div class="col-lg-6 col-5 text-right">
                        <a href="index.php" class="btn btn-sm btn-neutral">
                            <i class="fas fa-arrow-left mr-1"></i> Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--6">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        <form action="proses.php?action=tambah" method="POST" id="formTambahUser">
                            <div class="form-group">
                                <label>Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control" required>
                                <small class="text-muted">Harus unik, akan digunakan untuk login.</small>
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama_lengkap" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>No. Telepon</label>
                                <input type="text" name="no_telp" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Role <span class="text-danger">*</span></label>
                                <select name="role_id" id="role_id" class="form-control" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>" <?= ($role['id'] == 4) ? 'data-is-tim="1"' : '' ?>><?= htmlspecialchars($role['nama']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" name="is_aktif" class="form-check-input" checked>
                                <label class="form-check-label">Aktif</label>
                            </div>

                            <!-- ========== BAGIAN KHUSUS TIM RESPON ========== -->
                            <div id="divAnggotaTim" style="display: none; margin-top: 1rem;">
                                <hr>
                                <h5 class="text-primary"><i class="fas fa-users"></i> Penugasan ke Tim</h5>
                                <p class="text-muted small">User dengan role <strong>Tim Respon</strong> wajib ditugaskan ke salah satu tim.</p>
                                <div class="form-group">
                                    <label>Pilih Tim <span class="text-danger">*</span></label>
                                    <select name="tim_id" id="tim_id" class="form-control">
                                        <option value="">-- Pilih Tim --</option>
                                        <?php foreach ($tim_list as $tim): ?>
                                            <option value="<?= $tim['id'] ?>"><?= htmlspecialchars($tim['nama_tim']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Jabatan <span class="text-danger">*</span></label>
                                    <select name="jabatan" id="jabatan" class="form-control">
                                        <option value="">-- Pilih Jabatan --</option>
                                        <option value="dokter">Dokter</option>
                                        <option value="perawat">Perawat</option>
                                        <option value="bidan">Bidan</option>
                                        <option value="supir">Supir</option>
                                        <option value="operator_lapangan">Operator Lapangan</option>
                                    </select>
                                </div>
                            </div>

                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> Password default: <strong>123456</strong> (harus diubah setelah login pertama).
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan</button>
                            <a href="index.php" class="btn btn-secondary">Batal</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require '../template/footer.php'; ?>
    </div>
</div>

<script>
    const roleSelect = document.getElementById('role_id');
    const divTim = document.getElementById('divAnggotaTim');
    const timSelect = document.getElementById('tim_id');
    const jabatanSelect = document.getElementById('jabatan');

    function toggleTimField() {
        const selectedRole = roleSelect.options[roleSelect.selectedIndex];
        const isTim = selectedRole.getAttribute('data-is-tim') === '1' || selectedRole.value == 4;
        divTim.style.display = isTim ? 'block' : 'none';
        if (!isTim) {
            timSelect.removeAttribute('required');
            jabatanSelect.removeAttribute('required');
        } else {
            timSelect.setAttribute('required', 'required');
            jabatanSelect.setAttribute('required', 'required');
        }
    }

    roleSelect.addEventListener('change', toggleTimField);
    toggleTimField(); // inisialisasi

    // Validasi form sebelum submit: jika role tim respon maka tim dan jabatan harus diisi
    document.getElementById('formTambahUser').addEventListener('submit', function(e) {
        if (roleSelect.value == 4) {
            if (!timSelect.value || !jabatanSelect.value) {
                e.preventDefault();
                alert('Silakan pilih tim dan jabatan untuk user tim respon.');
            }
        }
    });
</script>

<style>
    .main-content { margin-left: 260px; }
    @media (max-width:768px){ .main-content{ margin-left:0; } }
</style>
<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../koneksi.php';

$nama_role = $_SESSION['nama_role'] ?? '';
if (!in_array($nama_role, ['admin', 'koordinator'])) {
    header('Location: ../dashboard.php');
    exit;
}

$id = (int)$_GET['id'];
if (!$id) {
    header('Location: index.php');
    exit;
}

// Ambil data user
$stmt = mysqli_prepare($koneksi, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
if (!$user) {
    $_SESSION['error'] = "Pengguna tidak ditemukan";
    header('Location: index.php');
    exit;
}

// Ambil data anggota tim aktif (jika ada) untuk user ini
$anggota = null;
$stmt_anggota = mysqli_prepare($koneksi, "SELECT tim_id, jabatan FROM anggota_tim WHERE user_id = ? AND tgl_keluar IS NULL LIMIT 1");
mysqli_stmt_bind_param($stmt_anggota, 'i', $id);
mysqli_stmt_execute($stmt_anggota);
$res_anggota = mysqli_stmt_get_result($stmt_anggota);
if ($row = mysqli_fetch_assoc($res_anggota)) {
    $anggota = $row;
}
mysqli_stmt_close($stmt_anggota);

$title = 'Edit Pengguna';
require '../template/header.php';
require '../template/sidebar.php';

$res_role = mysqli_query($koneksi, "SELECT * FROM roles ORDER BY id");
$roles = mysqli_fetch_all($res_role, MYSQLI_ASSOC);

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
                        <h6 class="h2 text-white d-inline-block mb-0"><i class="fas fa-user-edit mr-2"></i> Edit Pengguna</h6>
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
                        <form action="proses.php?action=edit" method="POST" id="formEditUser">
                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                <small class="text-muted">Username tidak bisa diubah.</small>
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap <span class="text-danger">*</span></label>
                                <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>No. Telepon</label>
                                <input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Role <span class="text-danger">*</span></label>
                                <select name="role_id" id="role_id" class="form-control" required>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?= $role['id'] ?>" 
                                            <?= $role['id'] == $user['role_id'] ? 'selected' : '' ?>
                                            <?= ($role['id'] == 4) ? 'data-is-tim="1"' : '' ?>>
                                            <?= htmlspecialchars($role['nama']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" name="is_aktif" class="form-check-input" <?= $user['is_aktif'] ? 'checked' : '' ?>>
                                <label class="form-check-label">Aktif</label>
                            </div>

                            <!-- Bagian untuk anggota tim (hanya jika role tim_respon) -->
                            <div id="divAnggotaTim" style="display: none; margin-top: 1rem;">
                                <hr>
                                <h5 class="text-primary"><i class="fas fa-users"></i> Penugasan ke Tim</h5>
                                <p class="text-muted small">User dengan role <strong>Tim Respon</strong> wajib ditugaskan ke salah satu tim.</p>
                                <div class="form-group">
                                    <label>Pilih Tim <span class="text-danger">*</span></label>
                                    <select name="tim_id" id="tim_id" class="form-control">
                                        <option value="">-- Pilih Tim --</option>
                                        <?php foreach ($tim_list as $tim): ?>
                                            <option value="<?= $tim['id'] ?>" <?= ($anggota && $anggota['tim_id'] == $tim['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($tim['nama_tim']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Jabatan <span class="text-danger">*</span></label>
                                    <select name="jabatan" id="jabatan" class="form-control">
                                        <option value="">-- Pilih Jabatan --</option>
                                        <option value="dokter" <?= ($anggota && $anggota['jabatan'] == 'dokter') ? 'selected' : '' ?>>Dokter</option>
                                        <option value="perawat" <?= ($anggota && $anggota['jabatan'] == 'perawat') ? 'selected' : '' ?>>Perawat</option>
                                        <option value="bidan" <?= ($anggota && $anggota['jabatan'] == 'bidan') ? 'selected' : '' ?>>Bidan</option>
                                        <option value="supir" <?= ($anggota && $anggota['jabatan'] == 'supir') ? 'selected' : '' ?>>Supir</option>
                                        <option value="operator_lapangan" <?= ($anggota && $anggota['jabatan'] == 'operator_lapangan') ? 'selected' : '' ?>>Operator Lapangan</option>
                                    </select>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary">Update</button>
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
        if (isTim) {
            timSelect.setAttribute('required', 'required');
            jabatanSelect.setAttribute('required', 'required');
        } else {
            timSelect.removeAttribute('required');
            jabatanSelect.removeAttribute('required');
            // jika role bukan tim, kita tidak wajibkan tim/jabatan
        }
    }
    roleSelect.addEventListener('change', toggleTimField);
    toggleTimField(); // inisialisasi

    // Validasi form: jika role tim_respon, tim dan jabatan harus diisi
    document.getElementById('formEditUser').addEventListener('submit', function(e) {
        if (roleSelect.value == 4) {
            if (!timSelect.value || !jabatanSelect.value) {
                e.preventDefault();
                alert('Untuk user tim respon, silakan pilih tim dan jabatan.');
            }
        }
    });
</script>

<style>
    .main-content { margin-left: 260px; }
    @media (max-width:768px){ .main-content{ margin-left:0; } }
</style>
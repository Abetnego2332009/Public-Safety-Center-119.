<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: index.php');
    exit;
}
require_once 'koneksi.php';

$user_id = $_SESSION['id'];
$role = $_SESSION['nama_role'] ?? '';

$query = "SELECT u.*, r.nama as role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = $user_id";
$user = mysqli_fetch_assoc(mysqli_query($koneksi, $query));
if (!$user) die("User tidak ditemukan.");

$tim_info = null;
if ($role == 'tim_respon') {
    $q = "SELECT at.jabatan, t.nama_tim, t.kode_tim 
          FROM anggota_tim at 
          JOIN tim t ON at.tim_id = t.id 
          WHERE at.user_id = $user_id AND at.tgl_keluar IS NULL";
    $tim_info = mysqli_fetch_assoc(mysqli_query($koneksi, $q));
}

$title = 'Profil Saya';
require 'template/header.php';
require 'template/sidebar.php';

// Tentukan URL foto profil (path absolut dari root)
$foto_db = !empty($user['foto_profil']) ? $user['foto_profil'] : '';
$foto_url = (!empty($foto_db)) ? 'uploads/profil/' . $foto_db : 'assets/assets/img/default.png';
?>

<div class="main-content">
    <?php require 'template/navbar.php'; ?>
    
    <div class="header pb-8 pt-5 pt-lg-8 d-flex align-items-center" 
         style="min-height: 200px; background-image: url(assets/assets/img/theme/profile-cover.jpg); background-size: cover; background-position: center top;">
        <span class="mask bg-gradient-default opacity-8"></span>
        <div class="container-fluid d-flex align-items-center">
            <div class="row">
                <div class="col-lg-12">
                    <h1 class="display-2 text-white">Profil Saya</h1>
                    <p class="text-white mt-0 mb-5">Kelola informasi akun dan password Anda</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid mt--7">
        <div class="row">
            <!-- Kolom kiri: card profil -->
            <div class="col-xl-4 order-xl-2 mb-5 mb-xl-0">
                <div class="card card-profile shadow">
                    <div class="row justify-content-center">
                        <div class="col-lg-3 order-lg-2">
                            <div class="card-profile-image">
                                <img src="<?= $foto_url ?>" class="rounded-circle" 
                                     style="width: 150px; height: 150px; object-fit: cover; border: 3px solid white; box-shadow: 0 3px 6px rgba(0,0,0,0.1);">
                            </div>
                        </div>
                    </div>
                    <div class="card-body pt-5">
                        <div class="text-center">
                            <h3><?= htmlspecialchars($user['nama_lengkap']) ?></h3>
                            <div class="h5 font-weight-300">
                                <i class="ni ni-single-02"></i> <?= ucfirst($user['role_name']) ?>
                            </div>
                            <div class="h5 mt-3">
                                <i class="ni ni-email-83"></i> <?= htmlspecialchars($user['email'] ?? '-') ?>
                            </div>
                            <div class="h5">
                                <i class="ni ni-mobile-button"></i> <?= htmlspecialchars($user['no_telp'] ?? '-') ?>
                            </div>
                            <?php if ($role == 'tim_respon' && $tim_info): ?>
                                <hr>
                                <div class="h5 text-info">
                                    <i class="ni ni-badge"></i> <?= htmlspecialchars($tim_info['nama_tim']) ?> (<?= $tim_info['kode_tim'] ?>)
                                </div>
                                <div class="h5 text-info">
                                    <i class="ni ni-hat-3"></i> <?= ucfirst(str_replace('_', ' ', $tim_info['jabatan'])) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom kanan: form edit -->
            <div class="col-xl-8 order-xl-1">
                <div class="card shadow">
                    <div class="card-header bg-white border-0">
                        <h3 class="mb-0">Edit Profil</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show">
                                <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="proses_profile.php" enctype="multipart/form-data">
                            <h6 class="heading-small text-muted mb-4">Informasi Akun</h6>
                            <div class="pl-lg-4">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Nama Lengkap</label>
                                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Username</label>
                                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-control-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label class="form-control-label">No. Telepon</label>
                                            <input type="text" name="no_telp" class="form-control" value="<?= htmlspecialchars($user['no_telp'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label class="form-control-label">Foto Profil</label>
                                            <div class="custom-file mb-2">
                                                <input type="file" class="custom-file-input" id="fotoProfil" name="foto_profil" accept="image/jpeg,image/png,image/jpg">
                                                <label class="custom-file-label" for="fotoProfil" id="fileLabel">Pilih file...</label>
                                            </div>
                                            <small class="text-muted">Maks 2MB (JPG/PNG). Kosongkan jika tidak ingin mengubah.</small>
                                            <div class="mt-3" id="previewArea">
                                                <img id="previewImg" src="<?= $foto_url ?>" 
                                                     style="max-width: 150px; max-height: 150px; object-fit: cover; border-radius: 10px; border: 1px solid #ddd; <?= empty($foto_db) ? 'display:none;' : '' ?>">
                                                <button type="button" id="clearPreviewBtn" class="btn btn-sm btn-outline-danger ml-2" style="<?= empty($foto_db) ? 'display:none;' : '' ?>">Hapus</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <hr class="my-4">
                            <h6 class="heading-small text-muted mb-4">Ganti Password</h6>
                            <div class="pl-lg-4">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Password Lama</label>
                                            <input type="password" name="password_lama" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Password Baru</label>
                                            <input type="password" name="password_baru" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label class="form-control-label">Konfirmasi Password Baru</label>
                                            <input type="password" name="konfirmasi_password" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right mt-4">
                                <button type="submit" name="update_profile" class="btn btn-primary">Simpan Perubahan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php require 'template/footer.php'; ?>
    </div>
</div>

<script>
    // Data dari server
    const fotoLamaNama = "<?= $foto_db ?>";  // nama file saja, bisa kosong
    const fotoLamaUrl = "<?= $foto_url ?>";  // full URL (uploads/profil/... atau default)
    const fileInput = document.getElementById('fotoProfil');
    const fileLabel = document.getElementById('fileLabel');
    const previewImg = document.getElementById('previewImg');
    const clearBtn = document.getElementById('clearPreviewBtn');

    function resetPreviewToOriginal() {
        fileInput.value = '';
        fileLabel.innerText = 'Pilih file...';
        if (fotoLamaNama) {
            previewImg.src = fotoLamaUrl;
            previewImg.style.display = 'inline-block';
            clearBtn.style.display = 'inline-block';
            clearBtn.innerText = 'Hapus';
            clearBtn.classList.remove('btn-secondary');
            clearBtn.classList.add('btn-outline-danger');
        } else {
            previewImg.style.display = 'none';
            clearBtn.style.display = 'none';
            previewImg.src = '';
        }
    }

    fileInput.addEventListener('change', function(e) {
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            fileLabel.innerText = file.name;
            const reader = new FileReader();
            reader.onload = function(ev) {
                previewImg.src = ev.target.result;
                previewImg.style.display = 'inline-block';
                clearBtn.style.display = 'inline-block';
                clearBtn.innerText = 'Batal';
                clearBtn.classList.remove('btn-outline-danger');
                clearBtn.classList.add('btn-outline-secondary');
            };
            reader.readAsDataURL(file);
        } else {
            resetPreviewToOriginal();
        }
    });

    function hapusFotoServer() {
        if (confirm("Yakin ingin menghapus foto profil? Tindakan ini permanen.")) {
            fetch('proses_profile.php?action=hapus_foto&user_id=<?= $user_id ?>', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.text())
            .then(data => {
                window.location.href = 'profile.php';
            })
            .catch(error => {
                alert('Gagal menghapus foto.');
            });
        }
    }

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (clearBtn.innerText === 'Batal') {
                resetPreviewToOriginal();
            } else if (clearBtn.innerText === 'Hapus') {
                hapusFotoServer();
            }
        });
    }

    // Jika tidak ada foto lama, sembunyikan tombol hapus
    if (!fotoLamaNama && clearBtn) {
        clearBtn.style.display = 'none';
    }
</script>

<style>
    .main-content { margin-left: 260px; }
    @media (max-width: 768px) { .main-content { margin-left: 0; } }
    .card-profile-image {
        position: relative;
        top: -60px;
    }
    .card-profile-image img {
        max-width: 150px;
        border-radius: 50%;
        border: 3px solid white;
        box-shadow: 0 3px 6px rgba(0,0,0,0.1);
    }
    .custom-file-label:after {
        content: "Browse";
    }
</style>
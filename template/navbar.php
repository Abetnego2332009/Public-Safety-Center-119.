<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($base_url)) $base_url = '';

$foto_profil = $_SESSION['foto_profil'] ?? '';
$foto_url = $base_url . 'assets/assets/img/default.png';

if (!empty($foto_profil)) {
    // Cek apakah file benar-benar ada di folder uploads/profil/
    $file_path = __DIR__ . '/../uploads/profil/' . $foto_profil;
    if (file_exists($file_path)) {
        $foto_url = $base_url . 'uploads/profil/' . $foto_profil;
    }
}
?>



<nav class="navbar navbar-top navbar-expand-md navbar-dark" id="navbar-main">
      <div class="container-fluid">
        <!-- Brand -->
        <P class="h4 mb-0 text-white text-uppercase d-none d-lg-inline-block">Dashboard</p>
        <!-- Form -->
        <!-- <form class="navbar-search navbar-search-dark form-inline mr-3 d-none d-md-flex ml-lg-auto">
          <div class="form-group mb-0">
            <div class="input-group input-group-alternative">
              <div class="input-group-prepend">
                <span class="input-group-text"><i class="fas fa-search"></i></span>
              </div>
              <input class="form-control" placeholder="Search" type="text">
            </div>
          </div>
        </form> -->
        <!-- User -->
        <ul class="navbar-nav align-items-center d-none d-md-flex">
          <li class="nav-item dropdown">
            <a class="nav-link pr-0" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
              <div class="media align-items-center">
                <span class="avatar avatar-sm rounded-circle">
                  <img src="<?= $foto_url ?>" alt="Foto Profil">
                </span>
                <div class="media-body ml-2 d-none d-lg-block">
                  <span class="mb-0 text-sm font-weight-bold"><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '') ?></span>
                </div>
              </div>
            </a>
            <div class="dropdown-menu dropdown-menu-arrow dropdown-menu-right">
              <div class="dropdown-header noti-title">
                <h6 class="text-overflow m-0">Welcome!</h6>
              </div>
              <a href="<?= $base_url ?>profile.php" class="dropdown-item"><i class="ni ni-single-02"></i><span>My profile</span></a>
              <!-- <a href="<?=$base_url?>examples/profile.html" class="dropdown-item"><i class="ni ni-settings-gear-65"></i><span>Settings</span></a>
              <a href="<?=$base_url?>examples/profile.html" class="dropdown-item"><i class="ni ni-calendar-grid-58"></i><span>Activity</span></a>
              <a href="<?=$base_url?>examples/profile.html" class="dropdown-item"><i class="ni ni-support-16"></i><span>Support</span></a> -->
              <div class="dropdown-divider"></div>
              <a href="<?= $base_url ?>logout.php" class="dropdown-item"><i class="ni ni-user-run"></i><span>Logout</span></a>
            </div>
          </li>
        </ul>
      </div>
    </nav>
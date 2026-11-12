<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>


<nav class="navbar navbar-vertical fixed-left navbar-expand-md navbar-light bg-white"
    id="sidenav-main">

    <div class="container-fluid">

        <!-- Tombol Hamburger untuk mobile -->
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#sidenav-collapse-main" aria-controls="sidenav-main" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- =================================================
        BRAND / LOGO
        ================================================== -->
        <a class="navbar-brand pt-3"
            href="<?= $base_url ?>dashboard.php"
            style="display:flex; flex-direction:column; align-items:center; text-align:center;">

            <img src="<?= $base_url ?>assets/assets/img/Logo.png"
                class="navbar-brand-img"
                style="max-height:5rem !important; height:80px; width:auto;">

            <span style="color:DodgerBlue; font-weight:700; font-size:18px; margin-top:8px;">
                PSC <span style="font-weight:400; opacity:.8;">119</span>
            </span>

        </a>

        <!-- =================================================
        COLLAPSE SIDEBAR
        ================================================== -->

        <div class="collapse navbar-collapse" id="sidenav-collapse-main">

            <ul class="navbar-nav">

                <!-- Dashboard — semua role -->
      
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>dashboard.php">
                        <i class="ni ni-tv-2 text-primary"></i>
                        Dashboard
                    </a>
                </li>
               

            </ul>

            <!-- =====================================================
            operator CENTER
            Hak Akses : operator, admin
            Tabel DB  : laporan, kasus, ref_jenis_kasus,
                        ref_triase, riwayat_status
            ====================================================== -->
            <?php if (
                $_SESSION['nama_role'] == 'admin' ||
                $_SESSION['nama_role'] == 'operator'
            ): ?>

                <h6 class="navbar-heading text-muted mt-4">operator Center</h6>

                <ul class="navbar-nav">

                    <!-- Input Laporan Darurat
                     Tabel: laporan, kasus, ref_jenis_kasus, riwayat_status -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>inputan_laporan/">
                            <i class="ni ni-headphones text-danger"></i>
                            Input Laporan Darurat
                        </a>
                    </li>

                    <!-- Laporan Masuk
                     Tabel: laporan, kasus, ref_jenis_kasus, ref_triase,
                            pengguna, jadwal_tim, kasus_pasien -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>laporan_masuk/">
                            <i class="ni ni-bullet-list-67 text-primary"></i>
                            Laporan Masuk
                        </a>
                    </li>

                    <!-- Penugasan Tim
                     Tabel: kasus, tim, jadwal_tim, riwayat_status -->
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>penugasan_tim/">
                            <i class="ni ni-delivery-fast text-warning"></i>
                            Penugasan Tim
                        </a>
                    </li> -->

                </ul>

            <?php endif; ?>


            <!-- =====================================================
            MEDICAL RESPONSE
            Hak Akses : tim_respon, admin
            Tabel DB  : kasus, kasus_pasien, pasien,
                        riwayat_status, lampiran
            ====================================================== -->
            <?php if (
                $_SESSION['nama_role'] == 'admin' ||
                $_SESSION['nama_role'] == 'tim_respon'
            ): ?>

                <h6 class="navbar-heading text-muted mt-4">Medical Response</h6>

                <ul class="navbar-nav">

                    <!-- Kasus Aktif
                     Tabel: kasus, laporan, ref_triase, ref_jenis_kasus, tim -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>kasus_aktif/">
                            <i class="ni ni-support-16 text-danger"></i>
                            Kasus Aktif
                        </a>
                    </li>

                    <!-- Update Penanganan & Foto
                     Tabel: kasus, riwayat_status, lampiran
                     (lampiran.jenis: FOTO_KONDISI / FOTO_EVAKUASI) -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>update_penanganan/">
                            <i class="ni ni-camera-compact text-orange"></i>
                            Update Penanganan
                        </a>
                    </li>
                    <!-- Data Pasien
                        Tabel: pasien, kasus_pasien, lampiran, ref_triase -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>data_pasien/">
                            <i class="ni ni-single-02 text-info"></i>
                            Data Pasien
                        </a>
                    </li>
                </ul>

            <?php endif; ?>


            <!-- =====================================================
            MONITORING & LAPORAN
            Hak Akses : koordinator, admin
            Tabel DB  : kasus, laporan, tim, ref_triase,
                        riwayat_status, kasus_pasien
            ====================================================== -->
            <?php if (
                $_SESSION['nama_role'] == 'admin' ||
                $_SESSION['nama_role'] == 'koordinator'
            ): ?>

                <h6 class="navbar-heading text-muted mt-4">Monitoring & Laporan</h6>

                <ul class="navbar-nav">

                    <!-- Monitoring Kasus (real-time semua kasus aktif)
                     Tabel: kasus, laporan, tim, ref_triase, jadwal_tim -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>monitoring_kasus/">
                            <i class="ni ni-world text-primary"></i>
                            Monitoring Kasus
                        </a>
                    </li>

                    <!-- Riwayat Kasus (kasus SELESAI / DIBATALKAN)
                     Tabel: kasus, laporan, pasien, kasus_pasien,
                            riwayat_status, lampiran -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>riwayat_kasus/">
                            <i class="ni ni-archive-2 text-warning"></i>
                            Riwayat Kasus
                        </a>
                    </li>

                    <!-- Rekap & Statistik
                     Tabel: kasus, laporan, ref_triase, ref_jenis_kasus -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>laporan/">
                            <i class="ni ni-chart-bar-32 text-danger"></i>
                            Rekap & Statistik
                        </a>
                    </li>

                </ul>

            <?php endif; ?>


            <!-- =====================================================
            MANAJEMEN TIM
            Hak Akses : koordinator, admin
            Tabel DB  : tim, anggota_tim, jadwal_tim, pengguna
            ====================================================== -->


            <?php if ($_SESSION['nama_role'] != 'operator'): ?>
                <h6 class="navbar-heading text-muted mt-4">Manajemen Tim</h6>

                <ul class="navbar-nav">
                    <!-- Jadwal Shift Harian (1 tim = 1 shift per hari)
                     Tabel: jadwal_tim, tim
                     Shift: SHIFT_1(08-14) | SHIFT_2(14-20) | SHIFT_3(20-08) -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>jadwal_tim/">
                            <i class="ni ni-calendar-grid-58 text-green"></i>
                            Jadwal Shift Tim
                        </a>
                    </li>

                <?php endif; ?>

                <?php if (
                    $_SESSION['nama_role'] == 'admin' ||
                    $_SESSION['nama_role'] == 'koordinator'
                ): ?>

                    <!-- Data Tim (Tim P, Tim S, Tim C)
                     Tabel: tim -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>tim/">
                            <i class="ni ni-badge text-primary"></i>
                            Data Tim
                        </a>
                    </li>

                    <!-- Anggota Tim
                     Tabel: anggota_tim, pengguna, tim -->
                    <!-- <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>anggota_tim/">
                            <i class="ni ni-circle-08 text-info"></i>
                            Anggota Tim
                        </a>
                    </li> -->



                </ul>
            <?php endif; ?>



            <!-- =====================================================
            MASTER DATA
            Hak Akses : admin
            Tabel DB  : ref_jenis_kasus, ref_triase,
                        pengguna, role
            ====================================================== -->

            <?php if (
                $_SESSION['nama_role'] == 'admin' ||
                $_SESSION['nama_role'] == 'koordinator'
            ): ?>
                <h6 class="navbar-heading text-muted mt-4">Master Data</h6>

                <ul class="navbar-nav">

                    <!-- Pengguna & Role
                     Tabel: pengguna, role -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>pengguna/">
                            <i class="ni ni-circle-08 text-pink"></i>
                            Pengguna & Role
                        </a>
                    </li>

                    <!-- Jenis Kasus
                     Tabel: ref_jenis_kasus
                     Contoh: KLL, CVD, STR, SPN, KMR, dll. -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>jenis_kasus/">
                            <i class="ni ni-ambulance text-danger"></i>
                            Jenis Kasus
                        </a>
                    </li>

                    <!-- Master Triase
                     Tabel: ref_triase
                     Nilai: MERAH(P1), KUNING(P2), HIJAU(P3), HITAM(P4) -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?= $base_url ?>triase/">
                            <i class="ni ni-chart-pie-35 text-red"></i>
                            Master Triase
                        </a>
                    </li>

                </ul>

            <?php endif; ?>


            <!-- =================================================
            ACCOUNT MENU — semua role
            ================================================== -->

            <!-- <h6 class="navbar-heading text-muted mt-4">Profile</h6>
            <ul class="navbar-nav">

                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>profile.php">
                        <i class="ni ni-single-02"></i>
                        Profile
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="<?= $base_url ?>logout.php">
                        <i class="ni ni-user-run text-red"></i>
                        Logout
                    </a>
                </li>

            </ul> -->

        </div>
    </div>
</nav>
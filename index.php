<?php
require 'koneksi.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>PSC 119 - Login</title>

    <!-- Favicon -->
    <link href="<?= $base_url ?>assets/assets/img/Logo.png" rel="icon" type="image/png">
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
    <!-- Icons -->
    <link href="<?= $base_url ?>assets/assets/js/plugins/nucleo/css/nucleo.css" rel="stylesheet" />
    <link href="<?= $base_url ?>assets/assets/js/plugins/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet" />
    <!-- Argon CSS -->
    <link href="<?= $base_url ?>assets/assets/css/argon-dashboard.css?v=1.1.2" rel="stylesheet" />

    <style>
        .navbar-vertical .navbar-brand-img,
        .navbar-vertical .navbar-brand > img {
            max-height: 5rem !important;
        }
    </style>
</head>

<body class="bg-default">
    <div class="main-content">

        <!-- Navbar Top -->
        <nav class="navbar navbar-top navbar-horizontal navbar-expand-md navbar-dark">
            <div class="container px-4">
                <button class="navbar-toggler" type="button" data-toggle="collapse"
                    data-target="#navbar-collapse-main" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbar-collapse-main"></div>
            </div>
        </nav>

        <!-- Header -->
        <div class="header bg-gradient-primary py-7 py-lg-8">
            <div class="container">
                <div class="header-body text-center mb-7">
                    <div class="row justify-content-center">
                        <div class="col-lg-5 col-md-6">
                            <h1 class="text-white">Welcome!</h1>
                            <p class="text-lead text-light">
                                Silahkan login menggunakan akun yang telah terdaftar.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="separator separator-bottom separator-skew zindex-100">
                <svg x="0" y="0" viewBox="0 0 2560 100" preserveAspectRatio="none"
                    version="1.1" xmlns="http://www.w3.org/2000/svg">
                    <polygon class="fill-default" points="2560 0 2560 100 0 100"></polygon>
                </svg>
            </div>
        </div>

        <!-- Page Content -->
        <div class="container mt--8 pb-5">
            <div class="row justify-content-center">
                <div class="col-lg-5 col-md-7">

                    <div class="card bg-secondary shadow border-0">

                        <!-- Logo Brand -->
                        <div class="card-header bg-transparent pb-3 pt-4">
                            <a class="navbar-brand" href="<?= $base_url ?>index.php"
                                style="display:flex; flex-direction:column; align-items:center; text-align:center; text-decoration:none;">
                                <img src="<?= $base_url ?>assets/assets/img/Logo.png"
                                    class="navbar-brand-img"
                                    style="height:80px; width:auto; max-height:5rem !important;">
                                <span style="color:DodgerBlue; font-weight:700; font-size:18px; margin-top:8px; letter-spacing:1px;">
                                    PSC <span style="font-weight:400; opacity:.8;">119</span>
                                </span>
                            </a>
                        </div>

                        <!-- Form Login -->
                        <div class="card-body px-lg-5 py-lg-5">
                            <div class="text-center text-muted mb-4">
                                <small>Masukkan email dan password kamu</small>
                            </div>

                            <form action="proses_login.php" method="POST" autocomplete="off">
                                <div class="form-group mb-3">
                                    <div class="input-group input-group-alternative">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="ni ni-email-83"></i>
                                            </span>
                                        </div>
                                        <input class="form-control" name="username" placeholder="USERNAME" type="text" required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="input-group input-group-alternative">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">
                                                <i class="ni ni-lock-circle-open"></i>
                                            </span>
                                        </div>
                                        <input class="form-control" name="password" placeholder="PASSWORD" type="password" required>
                                    </div>
                                </div>

                                <!-- <div class="custom-control custom-control-alternative custom-checkbox">
                                    <input class="custom-control-input" id="customCheckLogin" type="checkbox">
                                    <label class="custom-control-label" for="customCheckLogin">
                                        <span class="text-muted">Remember me</span>
                                    </label>
                                </div> -->

                                <div class="text-center">
                                    <button type="submit" name="login" class="btn btn-primary my-4">Sign In</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Links Bawah -->
                    <!-- <div class="row mt-3">
                        <div class="col-6">
                            <a href="#" class="text-light"><small>Lupa password?</small></a>
                        </div>
                        <div class="col-6 text-right">
                            <a href="#" class="text-light"><small>Buat akun baru</small></a>
                        </div>
                    </div> -->

                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="py-5">
            <div class="container">
                <div class="row align-items-center justify-content-xl-between">
                    <div class="col-xl-6">
                        <div class="copyright text-center text-xl-left text-muted">
                            © <?= date('Y') ?> PSC 119
                        </div>
                    </div>
                </div>
            </div>
        </footer>

    </div>

    <!-- Scripts -->
    <script src="<?= $base_url ?>assets/assets/js/plugins/jquery/dist/jquery.min.js"></script>
    <script src="<?= $base_url ?>assets/assets/js/plugins/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $base_url ?>assets/assets/js/argon-dashboard.min.js?v=1.1.2"></script>
</body>

</html>
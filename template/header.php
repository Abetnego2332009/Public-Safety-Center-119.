<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title><?= $title ?></title>
  <!-- Favicon -->
  
  <link href="<?= $base_url ?>assets/assets/img/Logo.png" rel="icon" type="image/png">
  <!-- Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700" rel="stylesheet">
  <!-- Icons -->
  <link href="<?=$base_url?>assets/assets/js/plugins/nucleo/css/nucleo.css" rel="stylesheet" />
  <link href="<?=$base_url?>assets/assets/js/plugins/@fortawesome/fontawesome-free/css/all.min.css" rel="stylesheet" />
  <!-- CSS Files -->
  <link href="<?=$base_url?>assets/assets/css/argon-dashboard.css?v=1.1.2" rel="stylesheet" />
  <link href="<?=$base_url?>assets/assets/css/input_laporan.css" rel="stylesheet" />
  <style>
  .navbar-vertical .navbar-brand-img,
  .navbar-vertical .navbar-brand > img {
    max-height: 5rem !important; /* 80px */
  }
</style>
<style>
  /* CSS PRINT GLOBAL - UNTUK SEMUA HALAMAN PSC119 */
@media print {
    /* Sembunyikan elemen navigasi & tombol */
    .sidebar, 
    .navbar, 
    .header, 
    .card-footer, 
    .btn, 
    button, 
    form, 
    .icon, 
    .breadcrumb,
    .dropdown,
    .footer,
    .alert,
    .pagination,
    .text-right,
    .col-5,
    .col-lg-6 .text-right,
    .card-header .row .col-md-6.text-right,
    .main-content .navbar,
    .navbar-vertical,
    .sidenav-main,
    [data-toggle="tooltip"],
    .page-item,
    .custom-file,
    .input-group,
    .input-group-prepend,
    .input-group-text {
        display: none !important;
    }

    /* Tampilkan header cetak (jika ada) */
    .print-header {
        display: block !important;
    }

    /* Reset margin & padding */
    body, .main-content, .container-fluid, .card, .card-body, .card-header {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
    }

    .main-content {
        margin-left: 0 !important;
        width: 100% !important;
        position: static !important;
    }

    .card {
        box-shadow: none !important;
        border: none !important;
    }

    /* Tabel rapi dan full lebar */
    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 10pt !important;
    }

    .table th, .table td {
        border: 1px solid #aaa !important;
        padding: 6px !important;
        text-align: left !important;
        vertical-align: top !important;
    }

    .table thead th {
        background-color: #f2f2f2 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* Badge agar tercetak */
    .badge {
        border: 1px solid #000 !important;
        color: #000 !important;
        background-color: #f8f9fa !important;
        padding: 2px 6px !important;
        border-radius: 12px !important;
    }
    .badge[style*="background"] {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        border: none !important;
        color: white !important;
    }
    /* Warna triase tertentu agar teks terbaca */
    .badge[style*="#D97706"], 
    .badge[style*="#16A34A"] {
        color: #000 !important;
    }

    /* Header cetak */
    .print-header-inner {
        text-align: center;
        margin-bottom: 20px;
    }
    .print-header-inner img {
        height: 60px;
    }
    hr {
        margin: 10px 0;
        border: 0.5px solid #ccc;
    }
}
</style>

</head>

<body class="">
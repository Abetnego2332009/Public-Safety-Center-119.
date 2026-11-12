<?php
// ============================================================
// export_excel_detail.php
// Digunakan untuk mengexport data kasus ke Excel
// ============================================================
require_once __DIR__ . '/../koneksi.php';
if (!isset($id_kasus)) exit;
// Query data kasus (sama seperti di detail_kasus.php)
$stmt = mysqli_prepare($koneksi, "
    SELECT 
        k.nomor_kasus, k.nama_pelapor, k.no_telp_pelapor, 
        k.lokasi_kejadian, k.deskripsi, k.waktu_kejadian, 
        k.status_terakhir, k.catatan_penutup,
        jk.nama AS nama_jenis,
        tr.label AS nama_triase,
        t.nama_tim,
        u.nama_lengkap AS nama_operator
    FROM kasus k
    JOIN jenis_kasus jk ON jk.id = k.jenis_kasus_id
    JOIN triase tr ON tr.id = k.triase_id
    JOIN users u ON u.id = k.operator_id
    LEFT JOIN tim t ON t.id = k.tim_id
    WHERE k.id = ?
");
mysqli_stmt_bind_param($stmt, 'i', $id_kasus);
mysqli_stmt_execute($stmt);
$kasus = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$stmt_pasien = mysqli_prepare($koneksi, "SELECT nama_pasien, nik, umur, jenis_kelamin, kondisi_awal FROM pasien WHERE kasus_id = ?");
mysqli_stmt_bind_param($stmt_pasien, 'i', $id_kasus);
mysqli_stmt_execute($stmt_pasien);
$pasien_list = mysqli_fetch_all(mysqli_stmt_get_result($stmt_pasien), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_pasien);
?>
<html>
<head>
<meta charset="UTF-8">
<title>Export Kasus <?= htmlspecialchars($kasus['nomor_kasus']) ?></title>
</head>
<body>
<h2>Detail Kasus - <?= htmlspecialchars($kasus['nomor_kasus']) ?></h2>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Nama Pelapor</th><td><?= htmlspecialchars($kasus['nama_pelapor']) ?></td></tr>
    <tr><th>Telepon</th><td><?= htmlspecialchars($kasus['no_telp_pelapor']) ?></td></tr>
    <tr><th>Lokasi</th><td><?= nl2br(htmlspecialchars($kasus['lokasi_kejadian'])) ?></td></tr>
    <tr><th>Waktu Kejadian</th><td><?= $kasus['waktu_kejadian'] ?></td></tr>
    <tr><th>Jenis Kasus</th><td><?= htmlspecialchars($kasus['nama_jenis']) ?></td></tr>
    <tr><th>Triase</th><td><?= htmlspecialchars($kasus['nama_triase']) ?></td></tr>
    <tr><th>Status</th><td><?= $kasus['status_terakhir'] ?></td></tr>
    <tr><th>Operator</th><td><?= htmlspecialchars($kasus['nama_operator']) ?></td></tr>
    <tr><th>Tim Bertugas</th><td><?= htmlspecialchars($kasus['nama_tim'] ?? '-') ?></td></tr>
    <tr><th>Deskripsi</th><td><?= nl2br(htmlspecialchars($kasus['deskripsi'])) ?></td></tr>
    <?php if ($kasus['catatan_penutup']): ?>
    <tr><th>Catatan Penutup</th><td><?= nl2br(htmlspecialchars($kasus['catatan_penutup'])) ?></td></tr>
    <?php endif; ?>
</table>

<h3>Data Pasien</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>#</th><th>Nama</th><th>NIK</th><th>Umur</th><th>Jenis Kelamin</th><th>Kondisi</th></tr>
    <?php foreach ($pasien_list as $i => $p): ?>
    <tr>
        <td><?= $i+1 ?></td>
        <td><?= htmlspecialchars($p['nama_pasien'] ?? '-') ?></td>
        <td><?= htmlspecialchars($p['nik'] ?? '-') ?></td>
        <td><?= $p['umur'] ? $p['umur'].' thn' : '-' ?></td>
        <td><?= $p['jenis_kelamin'] == 'L' ? 'Laki-laki' : ($p['jenis_kelamin'] == 'P' ? 'Perempuan' : '-') ?></td>
        <td><?= nl2br(htmlspecialchars($p['kondisi_awal'])) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
<?php
// ============================================================
//  FILE   : proses_login.php
//  SISTEM : PSC 119 – Pendataan Kasus Gawat Darurat
//  FUNGSI : Autentikasi pengguna, bangun session, redirect.
//
//  SKEMA DATABASE (db_psc119.sql — TELAH DIVERIFIKASI):
//  ┌──────────────────────────────────────────────────────────┐
//  │ users        : id, role_id, nama_lengkap, username,      │
//  │                password (bcrypt), email, no_telp,        │
//  │                foto_profil, is_aktif                     │
//  ├──────────────────────────────────────────────────────────┤
//  │ roles        : id, nama, deskripsi                       │
//  │   nama (lowercase): admin | operator |                   │
//  │                     koordinator | tim_respon             │
//  ├──────────────────────────────────────────────────────────┤
//  │ anggota_tim  : id, tim_id, user_id, jabatan,             │
//  │                tgl_masuk, tgl_keluar                     │
//  │   jabatan (ENUM): dokter | perawat | bidan |             │
//  │                   supir | operator_lapangan              │
//  │   AKTIF = tgl_keluar IS NULL (bukan kolom aktif!)        │
//  ├──────────────────────────────────────────────────────────┤
//  │ tim          : id, kode_tim, nama_tim, is_aktif          │
//  │   id=1 → TIM P | id=2 → TIM S | id=3 → TIM C            │
//  ├──────────────────────────────────────────────────────────┤
//  │ log_aktivitas: id, user_id, aksi, tabel_tujuan,          │
//  │                record_id, deskripsi, ip_address          │
//  └──────────────────────────────────────────────────────────┘
//
//  LOGIKA TIM_RESPON:
//  Semua anggota TIM P / TIM S / TIM C login pakai role
//  'tim_respon'. Tim mana → dicari dari anggota_tim
//  WHERE user_id = ? AND tgl_keluar IS NULL
//  Contoh: user 'medis' (id=3) → tim_id=1 → TIM P, jabatan=perawat
// ============================================================

session_start();
require_once 'koneksi.php';
date_default_timezone_set('Asia/Jakarta');

// ── Tolak akses bukan dari form POST ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    echo "<script>alert('Akses tidak diizinkan!'); window.location='index.php';</script>";
    exit;
}

// ── Ambil & sanitasi input ───────────────────────────────────
// username : trim + htmlspecialchars (aman untuk tampilan)
// password : HANYA trim — JANGAN htmlspecialchars karena akan
//            merusak karakter khusus (&, <, >, ", ') saat
//            dicocokkan ke bcrypt hash via password_verify()
$username   = trim(htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'));
$kata_sandi = trim($_POST['password'] ?? '');

// ── Validasi: field wajib diisi ──────────────────────────────
if ($username === '' || $kata_sandi === '') {
    echo "<script>alert('Username dan password wajib diisi!'); window.location='index.php';</script>";
    exit;
}

// ── Validasi: panjang username (VARCHAR(50) di DB) ───────────
if (strlen($username) > 50) {
    echo "<script>alert('Username tidak valid!'); window.location='index.php';</script>";
    exit;
}

// ============================================================
//  QUERY: Ambil data user + nama role
//
//  Tabel  : users  JOIN  roles
//  users.password  → bcrypt hash (VARCHAR 255)
//  users.is_aktif  → TINYINT(1): 1=aktif, 0=nonaktif
//  roles.nama      → lowercase: admin|operator|koordinator|tim_respon
// ============================================================
$sql = "SELECT
            u.id,
            u.role_id,
            u.nama_lengkap,
            u.username,
            u.password,
            u.no_telp,
            u.foto_profil,
            u.is_aktif,
            r.nama   AS nama_role
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        WHERE u.username = ?
        LIMIT 1";

$stmt = mysqli_prepare($koneksi, $sql);

if (!$stmt) {
    error_log('[PSC119] mysqli_prepare gagal: ' . mysqli_error($koneksi));
    echo "<script>alert('Terjadi kesalahan sistem. Silakan coba lagi.'); window.location='index.php';</script>";
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$hasil = mysqli_stmt_get_result($stmt);
$user  = mysqli_fetch_assoc($hasil);
mysqli_stmt_close($stmt);

// ── Validasi: username tidak ada di DB ───────────────────────
// Pesan sengaja DIGABUNG dengan validasi password — agar
// attacker tidak bisa menebak mana yang salah.
if (!$user) {
    echo "<script>alert('Username atau password salah!'); window.location='index.php';</script>";
    exit;
}

// ── Validasi: akun harus aktif ───────────────────────────────
// Kolom is_aktif: TINYINT(1) → 1=aktif, 0=nonaktif
if ((int) $user['is_aktif'] !== 1) {
    echo "<script>alert('Akun Anda dinonaktifkan. Hubungi administrator!'); window.location='index.php';</script>";
    exit;
}

// ── Validasi: cocokkan password dengan bcrypt hash ───────────
// Kolom password: VARCHAR(255), tersimpan sebagai bcrypt hash
if (!password_verify($kata_sandi, $user['password'])) {
    echo "<script>alert('Username atau password salah!'); window.location='index.php';</script>";
    exit;
}

// ── Validasi: role harus dikenali sistem ─────────────────────
// Nilai roles.nama: lowercase sesuai seed data di DB
$role_valid = ['admin', 'operator', 'koordinator', 'tim_respon'];

if (!in_array($user['nama_role'], $role_valid, true)) {
    echo "<script>alert('Role akun tidak dikenali. Hubungi administrator!'); window.location='index.php';</script>";
    exit;
}

// ============================================================
//  LOGIN BERHASIL — BANGUN SESSION
// ============================================================

// Cegah Session Fixation Attack
session_regenerate_id(true);

$user_id = (int) $user['id'];

// ── Session inti ─────────────────────────────────────────────
//  Key session yang KONSISTEN dipakai di seluruh halaman:
//
//  $_SESSION['user_id']      → PK dari tabel users
//  $_SESSION['role_id']      → FK ke tabel roles (integer)
//  $_SESSION['nama_role']    → admin|operator|koordinator|tim_respon
//  $_SESSION['nama_lengkap'] → nama tampilan
//  $_SESSION['username']     → username login
//  $_SESSION['no_telp']      → bisa NULL
//  $_SESSION['foto_profil']  → path foto, bisa NULL
//  $_SESSION['sudah_login']  → flag pengecekan guard halaman lain
//  $_SESSION['waktu_login']  → timestamp login
//
//  Contoh guard di halaman lain:
//    if (empty($_SESSION['sudah_login'])) {
//        header('Location: ../index.php'); exit;
//    }
//    if ($_SESSION['nama_role'] !== 'operator') {
//        header('Location: ../403.php'); exit;
//    }

$_SESSION['id']      = $user_id;
$_SESSION['role_id']      = (int) $user['role_id'];
$_SESSION['nama_role']    = $user['nama_role'];       // admin|operator|koordinator|tim_respon
$_SESSION['nama_lengkap'] = $user['nama_lengkap'];
$_SESSION['username']     = $user['username'];
$_SESSION['no_telp']      = $user['no_telp'];         // bisa NULL
$_SESSION['foto_profil']  = $user['foto_profil'];     // bisa NULL
$_SESSION['sudah_login']  = true;
$_SESSION['waktu_login']  = date('Y-m-d H:i:s');

// ============================================================
//  PROFIL TAMBAHAN PER ROLE
//
//  tim_respon → cari tim aktif dari anggota_tim
//    Kondisi aktif: tgl_keluar IS NULL
//    JOIN tim untuk nama_tim, kode_tim
//    Simpan tim_id ke session → dipakai filter kasus di halaman lain
//
//  Semua role mendapat $_SESSION['tim_id'] = null kecuali tim_respon
// ============================================================
switch ($user['nama_role']) {

    // ── TIM_RESPON ───────────────────────────────────────────
    //  Logika:
    //  - user 'medis' (id=3, role=tim_respon)
    //  - anggota_tim: user_id=3, tim_id=1, tgl_keluar=NULL
    //  - JOIN tim: id=1 → kode_tim='P', nama_tim='TIM P'
    //  - Hasil: $_SESSION['tim_id'] = 1
    //           $_SESSION['nama_tim'] = 'TIM P'
    //  - Di halaman kasus: WHERE k.tim_id = $_SESSION['tim_id']
    case 'tim_respon':
        $sql_tim = "SELECT
                        at.tim_id,
                        at.jabatan,
                        t.kode_tim,
                        t.nama_tim
                    FROM anggota_tim at
                    INNER JOIN tim t ON t.id = at.tim_id
                    WHERE at.user_id    = ?
                      AND at.tgl_keluar IS NULL
                      AND t.is_aktif    = 1
                    ORDER BY at.tgl_masuk DESC
                    LIMIT 1";

        $stmt_tim = mysqli_prepare($koneksi, $sql_tim);
        mysqli_stmt_bind_param($stmt_tim, 'i', $user_id);
        mysqli_stmt_execute($stmt_tim);
        $data_tim = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tim));
        mysqli_stmt_close($stmt_tim);

        // Simpan ke session — NULL jika belum masuk tim manapun
        $_SESSION['tim_id']   = $data_tim ? (int) $data_tim['tim_id'] : null;
        $_SESSION['kode_tim'] = $data_tim['kode_tim'] ?? null;   // P | S | C
        $_SESSION['nama_tim'] = $data_tim['nama_tim'] ?? null;   // TIM P | TIM S | TIM C
        $_SESSION['jabatan']  = $data_tim['jabatan']  ?? null;   // dokter|perawat|bidan|supir|operator_lapangan

        $_SESSION['profil'] = [
            'jenis'    => 'tim_respon',
            'tim_id'   => $_SESSION['tim_id'],
            'kode_tim' => $_SESSION['kode_tim'],
            'nama_tim' => $_SESSION['nama_tim'],
            'jabatan'  => $_SESSION['jabatan'],
        ];
        break;

    // ── OPERATOR ─────────────────────────────────────────────
    case 'operator':
        $_SESSION['tim_id']   = null;
        $_SESSION['kode_tim'] = null;
        $_SESSION['nama_tim'] = null;
        $_SESSION['jabatan']  = null;
        $_SESSION['profil']   = ['jenis' => 'operator'];
        break;

    // ── KOORDINATOR ──────────────────────────────────────────
    case 'koordinator':
        $_SESSION['tim_id']   = null;
        $_SESSION['kode_tim'] = null;
        $_SESSION['nama_tim'] = null;
        $_SESSION['jabatan']  = null;
        $_SESSION['profil']   = ['jenis' => 'koordinator'];
        break;

    // ── ADMIN ─────────────────────────────────────────────────
    case 'admin':
        $_SESSION['tim_id']   = null;
        $_SESSION['kode_tim'] = null;
        $_SESSION['nama_tim'] = null;
        $_SESSION['jabatan']  = null;
        $_SESSION['profil']   = ['jenis' => 'admin'];
        break;

    default:
        $_SESSION['tim_id']  = null;
        $_SESSION['profil']  = [];
        break;
}

// ============================================================
//  CATAT LOG AKTIVITAS
//
//  Tabel log_aktivitas ADA di DB dengan kolom:
//    user_id, aksi, tabel_tujuan, record_id, deskripsi, ip_address
//
//  Untuk event LOGIN:
//    tabel_tujuan → tidak relevan, tidak di-INSERT (pakai default NULL)
//    record_id    → tidak relevan, tidak di-INSERT (pakai default NULL)
// ============================================================
$ip_address    = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$deskripsi_log = 'Login berhasil via halaman index.php';

$sql_log = "INSERT INTO log_aktivitas
                (user_id, aksi, deskripsi, ip_address)
            VALUES
                (?, 'LOGIN', ?, ?)";

$stmt_log = mysqli_prepare($koneksi, $sql_log);
if ($stmt_log) {
    mysqli_stmt_bind_param($stmt_log, 'iss',
        $user_id,
        $deskripsi_log,
        $ip_address
    );
    mysqli_stmt_execute($stmt_log);
    mysqli_stmt_close($stmt_log);
}
// Kegagalan log TIDAK menghentikan proses login

// ============================================================
//  REDIRECT BERDASARKAN ROLE
//  Semua role ke dashboard yang sama.
//  Konten dashboard disesuaikan berdasarkan $_SESSION['nama_role']
// ============================================================
$tujuan = [
    'admin'       => 'dashboard.php',
    'koordinator' => 'dashboard.php',
    'operator'    => 'inputan_laporan/',
    'tim_respon'  => 'dashboard.php',
];

$halaman_tujuan = $tujuan[$user['nama_role']] ?? 'dashboard.php';
$nama_sambutan  = htmlspecialchars($user['nama_lengkap'], ENT_QUOTES, 'UTF-8');

// Pesan sambutan tambahan khusus tim_respon
$info_tim = '';
if ($user['nama_role'] === 'tim_respon') {
    if (!empty($_SESSION['nama_tim'])) {
        $jabatan_label = ucfirst(str_replace('_', ' ', $_SESSION['jabatan'] ?? ''));
        $info_tim = '\\n' . $_SESSION['nama_tim'] . ' (' . $_SESSION['kode_tim'] . ')';
        if ($jabatan_label) {
            $info_tim .= ' — ' . $jabatan_label;
        }
    } else {
        $info_tim = '\\n⚠ Anda belum ditugaskan ke tim manapun.';
    }
}

echo "<script>
    alert('Selamat datang, {$nama_sambutan}!{$info_tim}');
    window.location='{$halaman_tujuan}';
</script>";
exit;

// ============================================================
//  END OF FILE — proses_login.php
//  PSC 119 — Pematangsiantar
// ============================================================
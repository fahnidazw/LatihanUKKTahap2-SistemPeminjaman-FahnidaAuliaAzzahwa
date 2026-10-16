<?php
// Proses login: cocokkan dengan tabel `users` (kolom username / no_telp, password, level)
require __DIR__ . '/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') go('index.php');

function gagal($tipe, $pesan, $username = '') {
    $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan, 'username' => $username];
    go('index.php');
}

// 1. Cek token CSRF
if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
    gagal('umum', 'Sesi sudah berakhir. Muat ulang halaman lalu coba masuk lagi.');
}

// 2. Batasi percobaan: 5x gagal = tunggu 60 detik
if (($_SESSION['gagal'] ?? 0) >= 5 && time() - ($_SESSION['gagal_waktu'] ?? 0) < 60) {
    gagal('umum', 'Terlalu banyak percobaan. Tunggu 1 menit lalu coba lagi.');
}

$username = substr(trim($_POST['username'] ?? ''), 0, 50);
$password = $_POST['password'] ?? '';
if ($username === '' || $password === '') gagal('umum', 'Username dan password wajib diisi.', $username);

// 3. Cari akun lewat username ATAU nomor telepon
$u = q("SELECT * FROM users WHERE username=? OR no_telp=? LIMIT 1", 'ss', $username, $username)->fetch_assoc();
if (!$u) gagal('belum_terdaftar', '', $username);

// 4. Cek password (bcrypt untuk user baru, MD5 untuk data awal)
$ok = strlen($u['password']) === 32
    ? hash_equals($u['password'], md5($password))
    : password_verify($password, $u['password']);

if (!$ok) {
    $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;
    $_SESSION['gagal_waktu'] = time();
    gagal('password_salah', 'Password salah. Periksa kembali lalu coba lagi.', $username);
}

// 5. Berhasil
$token = $_SESSION['csrf_token'];
session_regenerate_id(true);
$_SESSION['csrf_token'] = $token;
unset($_SESSION['gagal'], $_SESSION['gagal_waktu']);
$_SESSION['u'] = ['id_user' => (int)$u['id_user'], 'nama' => $u['nama_lengkap'], 'level' => $u['level']];
logact('Login ke sistem');

$tujuan = ['admin' => 'admin/index.php', 'petugas' => 'petugas/index.php', 'peminjam' => 'peminjam/index.php'];
go($tujuan[$u['level']] ?? 'index.php');

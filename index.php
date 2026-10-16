<?php
// index.php - Halaman login & Proses Autentikasi Sarpras Sekolah
require __DIR__ . '/koneksi.php';

// Tentukan satu dashboard bersama untuk semua level setelah login berhasil (menggunakan path relatif yang benar)
$shared_dashboard = 'petugas/index.php';

// 1. Jika sudah login, langsung arahkan ke dashboard bersama
if (!empty($_SESSION['u']['level'])) {
    if (isset($dashboard[$_SESSION['u']['level']])) {
        go($shared_dashboard);
    } else {
        go($shared_dashboard);
    }
}

// 2. Proses Form POST (Login Action Handler)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    function gagal($tipe, $pesan, $username = '') {
        $_SESSION['flash'] = ['tipe' => $tipe, 'pesan' => $pesan, 'username' => $username];
        go('index.php');
    }

    // Cek token CSRF
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        gagal('umum', 'Sesi sudah berakhir. Muat ulang halaman lalu coba masuk lagi.');
    }

    // Batasi percobaan: 5x gagal = tunggu 60 detik
    if (($_SESSION['gagal'] ?? 0) >= 5 && time() - ($_SESSION['gagal_waktu'] ?? 0) < 60) {
        gagal('umum', 'Terlalu banyak percobaan. Tunggu 1 menit lalu coba lagi.');
    }

    $username = substr(trim($_POST['username'] ?? ''), 0, 50);
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        gagal('umum', 'Username dan password wajib diisi.', $username);
    }

    // Cari akun lewat username ATAU nomor telepon
    $u = q("SELECT * FROM users WHERE username=? OR no_telp=? LIMIT 1", 'ss', $username, $username)->fetch_assoc();
    if (!$u) {
        gagal('belum_terdaftar', '', $username);
    }

    // Cek password (bcrypt untuk user baru, MD5 untuk data awal)
    $ok = strlen($u['password']) === 32
        ? hash_equals($u['password'], md5($password))
        : password_verify($password, $u['password']);

    if (!$ok) {
        $_SESSION['gagal'] = ($_SESSION['gagal'] ?? 0) + 1;
        $_SESSION['gagal_waktu'] = time();
        gagal('password_salah', 'Password salah. Periksa kembali lalu coba lagi.', $username);
    }

    // Login Berhasil
    $token = $_SESSION['csrf_token'];
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = $token;
    unset($_SESSION['gagal'], $_SESSION['gagal_waktu']);
    
    // Simpan session user lengkap dengan levelnya untuk pengaturan hak akses di dashboard umum
    $_SESSION['u'] = [
        'id_user' => (int)$u['id_user'], 
        'nama' => $u['nama_lengkap'], 
        'level' => $u['level'] // 'admin', 'petugas', atau 'peminjam'
    ];
    
    // Catat ke log aktivitas
    if (function_exists('logact')) {
        logact('Login ke sistem sebagai ' . $u['level']);
    }

    // Arahkan ketiganya ke satu tampilan umum / dashboard bersama
    go($shared_dashboard);
}

// 3. Token CSRF untuk Form View
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Hasil login gagal (tampil sekali)
$pesan = ''; $modalBelumTerdaftar = false; $usernameLama = ''; $fokusPassword = false;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (is_array($flash)) {
    $tipe = is_string($flash['tipe'] ?? null) ? $flash['tipe'] : '';
    if (is_string($flash['username'] ?? null)) $usernameLama = substr($flash['username'], 0, 50);
    if ($tipe === 'belum_terdaftar') $modalBelumTerdaftar = true;
    elseif (is_string($flash['pesan'] ?? null)) { 
        $pesan = substr($flash['pesan'], 0, 160); 
        $fokusPassword = ($tipe === 'password_salah'); 
    }
}
$autofokusUsername = !$modalBelumTerdaftar && !$fokusPassword;

// 5. Foto banner opsional
$fotoBanner = is_file(__DIR__ . '/asset/img/banner.jpg') ? 'asset/img/banner.jpg' : null;
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk - Sarpras Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
:root{
 --ink:#0D2B2E; --teal:#0F5F58; --teal-deep:#0A3A36; --sun:#FFC54D; --sun-deep:#B47A00; --sun-soft:#FFE3A1;
 --cream:#EAF5F1; --ivory:#FCFFFE; --line:#D3E6E0; --muted:#5F7A78;
 --serif:'Cormorant Garamond',Georgia,serif; --sans:'Plus Jakarta Sans',system-ui,sans-serif;
 --grad:linear-gradient(135deg,#0A4A46 0%,#0F7D70 55%,#17A38D 115%);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);color:var(--ink);min-height:100vh;display:grid;place-items:center;padding:24px 16px;-webkit-font-smoothing:antialiased;
 background:radial-gradient(900px 500px at 12% -10%,rgba(255,197,77,.25),transparent 60%),radial-gradient(800px 500px at 100% 110%,rgba(15,157,138,.20),transparent 60%),var(--cream)}

.card{width:100%;max-width:900px;display:grid;grid-template-columns:1fr 1fr;background:var(--ivory);border-radius:24px;overflow:hidden;
 box-shadow:0 0 0 1px rgba(15,95,88,.2),0 30px 60px -25px rgba(10,58,54,.5),0 12px 24px -14px rgba(10,58,54,.3);animation:masuk .7s cubic-bezier(.2,.7,.2,1) both}
@keyframes masuk{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}

.banner{position:relative;isolation:isolate;min-height:560px;padding:38px 36px 42px;display:flex;flex-direction:column;justify-content:space-between;color:#E6F5F0;
 background:linear-gradient(160deg,#0F6A61 0%,#0A3F3B 58%,#06221F 100%)}
.banner-foto{position:absolute;inset:0;z-index:-4;background-size:cover;background-position:center;opacity:.6}
.scene{position:absolute;inset:0;z-index:-3;width:100%;height:100%}
.banner::before{content:"";position:absolute;inset:0;z-index:-2;
 background:radial-gradient(420px 280px at 85% 8%,rgba(255,197,77,.28),transparent 65%),linear-gradient(to top,rgba(5,26,24,.95) 0%,rgba(5,26,24,.6) 40%,rgba(5,26,24,0) 72%)}
.banner::after{content:"";position:absolute;inset:12px;z-index:-1;border:1px solid rgba(255,197,77,.4);border-radius:16px;pointer-events:none}
.brand{display:flex;align-items:center;gap:12px;margin-top:10px}
.brand-mark{width:42px;height:42px;border-radius:14px;display:grid;place-items:center;font-family:var(--serif);font-weight:700;font-size:24px;color:var(--ink);background:var(--sun);transform:rotate(-6deg)}
.brand-name{font-family:var(--serif);font-weight:700;font-size:21px;letter-spacing:.22em;text-transform:uppercase;color:#fff}
.brand-sub{font-size:10px;letter-spacing:.24em;text-transform:uppercase;color:var(--sun);margin-top:2px}
.banner-isi{margin-bottom:12px}
.banner h2{font-family:var(--serif);font-weight:600;font-size:clamp(28px,3vw,36px);line-height:1.12;color:#fff}
.ornamen{display:flex;align-items:center;gap:10px;margin:16px 0 14px}
.ornamen i{height:1px;width:44px;background:linear-gradient(90deg,var(--sun),transparent)}
.ornamen i:last-child{background:linear-gradient(270deg,var(--sun),transparent)}
.ornamen b{width:7px;height:7px;background:var(--sun);transform:rotate(45deg);box-shadow:0 0 0 2px rgba(255,197,77,.25)}
.banner p{font-size:13px;line-height:1.65;color:rgba(230,245,240,.86);max-width:340px}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin-top:18px}
.chip{font-size:10.5px;letter-spacing:.12em;text-transform:uppercase;padding:5px 12px;border-radius:999px;color:var(--sun-soft);border:1px solid rgba(255,197,77,.45);background:rgba(255,197,77,.08)}

.panel{padding:48px 44px;display:flex;flex-direction:column;justify-content:center;background:radial-gradient(380px 220px at 100% 0%,rgba(255,197,77,.12),transparent 70%),var(--ivory)}
.eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:10.5px;font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:var(--sun-deep)}
.eyebrow::before{content:"";width:6px;height:6px;background:var(--sun);transform:rotate(45deg)}
.panel h1{font-family:var(--serif);font-weight:700;font-size:36px;line-height:1.08;margin:10px 0 8px}
.lead{font-size:13.5px;color:var(--muted);line-height:1.6;margin-bottom:24px}
.alert{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;margin-bottom:18px;border-radius:12px;background:#FFEFEC;border:1px solid #F5CAC3;color:#8A2F22;font-size:12.5px;line-height:1.45}
.alert svg{flex:none;width:16px;height:16px;margin-top:1px}
.field{margin-bottom:16px}
.field label{display:block;margin-bottom:6px;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#3F5C59}
.input-wrap{position:relative}
.ikon{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:17px;height:17px;color:#8AA5A1;pointer-events:none;transition:color .2s}
.input-wrap input{width:100%;height:48px;padding:0 44px 0 42px;font:500 14px var(--sans);color:var(--ink);background:var(--ivory);border:1px solid var(--line);border-radius:12px;outline:none;transition:border-color .2s,box-shadow .2s,background .2s}
.input-wrap input::placeholder{color:#9DB5B1;font-weight:400;font-size:13px}
.input-wrap input:hover{border-color:#B9D4CC}
.input-wrap input:focus{border-color:var(--teal);background:#fff;box-shadow:0 0 0 3px rgba(15,157,138,.18)}
.input-wrap:focus-within .ikon{color:var(--teal)}
.lihat{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:34px;height:34px;display:grid;place-items:center;border:0;border-radius:8px;background:transparent;color:#8AA5A1;cursor:pointer;transition:.2s}
.lihat:hover{background:rgba(15,157,138,.12);color:var(--teal)}
.lihat svg{width:18px;height:18px}
.btn{width:100%;height:50px;margin-top:6px;display:inline-flex;align-items:center;justify-content:center;gap:8px;font:600 14px var(--sans);letter-spacing:.03em;color:#fff;border:0;border-radius:12px;cursor:pointer;
 background:var(--grad);background-size:140% 100%;box-shadow:0 12px 24px -12px rgba(10,74,70,.8),inset 0 1px 0 rgba(255,255,255,.25);transition:transform .2s,box-shadow .2s,background-position .5s}
.btn:hover{transform:translateY(-1px);background-position:100% 0;box-shadow:0 18px 28px -14px rgba(10,74,70,.85)}
.btn:disabled{opacity:.75;cursor:wait}
.btn svg{width:17px;height:17px;transition:transform .2s}.btn:hover svg{transform:translateX(3px)}
.demo{margin-top:20px;padding:12px 14px;border-radius:12px;background:var(--cream);border:1px dashed #A9CFC5;font-size:12px;color:var(--teal);line-height:1.7}
.footer{margin-top:22px;padding-top:16px;border-top:1px solid var(--line);text-align:center;font-size:11px;letter-spacing:.05em;color:#8AA5A1}

.btn:focus-visible,.lihat:focus-visible,.tombol:focus-visible{outline:2px solid var(--sun-deep);outline-offset:2px}
body.modal-terbuka{overflow:hidden}
.modal{position:fixed;inset:0;z-index:100;display:none;align-items:center;justify-content:center;padding:20px;background:rgba(6,34,31,.6);backdrop-filter:blur(6px)}
.modal.tampil{display:flex;animation:fade .25s ease both}
.modal-kartu{position:relative;width:100%;max-width:410px;max-height:calc(100vh - 40px);overflow-y:auto;padding:36px 30px 26px;text-align:center;
 background:radial-gradient(340px 190px at 50% -8%,rgba(255,197,77,.22),transparent 70%),var(--ivory);border-radius:22px;
 box-shadow:0 0 0 1px rgba(15,95,88,.35),0 44px 80px -30px rgba(3,20,18,.8);animation:naik .38s cubic-bezier(.2,.8,.2,1) both}
.modal-kartu::before{content:"";position:absolute;inset:8px;pointer-events:none;border:1px solid rgba(255,197,77,.4);border-radius:15px}
.modal-kartu::after{content:"";position:absolute;top:0;left:50%;width:90px;height:3px;transform:translateX(-50%);background:linear-gradient(90deg,transparent,var(--sun),transparent)}
.modal-ikon{width:66px;height:66px;margin:0 auto 16px;border-radius:50%;display:grid;place-items:center;color:var(--sun-soft);background:linear-gradient(145deg,#0F7D70,#0A3A36);box-shadow:0 0 0 4px rgba(255,197,77,.18),0 0 0 5px rgba(255,197,77,.5),0 14px 22px -10px rgba(10,58,54,.7)}
.modal-ikon svg{width:30px;height:30px}
.modal-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:10px;font-weight:600;letter-spacing:.22em;text-transform:uppercase;color:var(--sun-deep)}
.modal-eyebrow::before,.modal-eyebrow::after{content:"";width:5px;height:5px;background:var(--sun);transform:rotate(45deg)}
.modal-kartu h2{font-family:var(--serif);font-weight:700;font-size:27px;line-height:1.1;margin:10px 0;text-wrap:balance}
.modal-kartu p{font-size:13.5px;line-height:1.65;color:var(--muted)}
.modal-nomor{display:inline-block;max-width:100%;margin-top:14px;padding:6px 14px;border-radius:999px;background:var(--cream);border:1px solid var(--line);font-size:12.5px;font-weight:600;color:var(--teal);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.modal-aksi{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:24px}
.modal-aksi.satu{grid-template-columns:1fr}
.tombol{height:46px;padding:0 14px;border-radius:12px;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font:600 13.5px var(--sans);transition:.2s}
.tombol.utama{border:0;color:#fff;background:var(--grad);box-shadow:0 12px 22px -12px rgba(10,74,70,.8)}
.tombol.utama:hover{transform:translateY(-1px)}
.tombol.garis{background:transparent;color:var(--teal);border:1px solid rgba(15,95,88,.45)}
.tombol.garis:hover{background:rgba(15,157,138,.1)}
.langkah{list-style:none;margin:18px 0 0;text-align:left}
.langkah li{position:relative;display:flex;gap:14px;align-items:flex-start;padding:9px 0;font-size:13px;line-height:1.55;color:#34504D}
.langkah li b{font-weight:600;color:var(--ink)}
.langkah .no{flex:none;width:28px;height:28px;border-radius:50%;display:grid;place-items:center;position:relative;z-index:1;font:700 12.5px var(--sans);color:var(--teal-deep);background:linear-gradient(145deg,#FFE9B0,#FFC54D);box-shadow:0 0 0 3px rgba(255,197,77,.2)}
.langkah li:not(:last-child)::after{content:"";position:absolute;left:13.5px;top:37px;bottom:-9px;width:1px;background:linear-gradient(var(--sun),rgba(255,197,77,.15))}
.langkah .teks{padding-top:4px}
@keyframes fade{from{opacity:0}to{opacity:1}}
@keyframes naik{from{opacity:0;transform:translateY(16px) scale(.97)}to{opacity:1;transform:none}}

@media(max-width:840px){
 .card{grid-template-columns:1fr;max-width:480px}
 .banner{min-height:0;padding:32px 24px 44px}
 .banner p,.chips,.ornamen{display:none}
 .banner h2{font-size:24px;margin-top:18px}
 .panel{padding:32px 24px}.panel h1{font-size:30px}
}
@media(max-width:440px){.modal-kartu{padding:32px 22px 22px}.modal-aksi{grid-template-columns:1fr}.modal-aksi .garis{order:2}}
@media(prefers-reduced-motion:reduce){.card,.modal.tampil,.modal-kartu{animation:none!important}}
</style>
</head>
<body>
<main class="card">

 <!-- Kiri: banner ilustrasi gedung sekolah -->
 <section class="banner" aria-label="Sarpras Sekolah">
  <?php if ($fotoBanner): ?><div class="banner-foto" style="background-image:url('<?= e($fotoBanner) ?>')"></div><?php endif; ?>
  <svg class="scene" aria-hidden="true" viewBox="0 0 400 560" preserveAspectRatio="xMidYMin slice" xmlns="http://www.w3.org/2000/svg">
   <defs>
    <pattern id="ubin" width="28" height="28" patternUnits="userSpaceOnUse"><path d="M28 0H0V28" fill="none" stroke="#FFC54D" stroke-opacity=".10"/></pattern>
    <linearGradient id="tanah" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#06221F"/><stop offset="1" stop-color="#06221F" stop-opacity="0"/></linearGradient>
   </defs>
   <rect width="400" height="560" fill="url(#ubin)"/>
   <circle cx="318" cy="104" r="64" fill="#FFC54D" opacity=".14"/>
   <circle cx="318" cy="104" r="40" fill="#FFC54D" opacity=".92"/>
   <path d="M0 300 Q70 250 140 285 T290 270 T400 295 V340 H0Z" fill="#0F7D70" opacity=".45"/>
   <g opacity=".9">
    <polygon points="52,214 200,146 348,214" fill="#12857A"/>
    <rect x="70" y="214" width="260" height="118" fill="#0B4F4A"/>
    <rect x="70" y="214" width="260" height="8" fill="#0F6E64"/>
    <circle cx="200" cy="190" r="13" fill="#FFC54D"/><path d="M200 182v8l5 3" stroke="#0A3A36" stroke-width="2" fill="none" stroke-linecap="round"/>
    <line x1="200" y1="146" x2="200" y2="112" stroke="#E6F5F0" stroke-width="2"/>
    <polygon points="200,112 228,120 200,128" fill="#FF6B57"/>
    <g fill="#FFC54D"><rect x="88" y="234" width="24" height="26" rx="3"/><rect x="124" y="234" width="24" height="26" rx="3"/><rect x="156" y="234" width="20" height="26" rx="3" opacity=".6"/>
     <rect x="224" y="234" width="20" height="26" rx="3" opacity=".6"/><rect x="252" y="234" width="24" height="26" rx="3"/><rect x="288" y="234" width="24" height="26" rx="3"/>
     <rect x="88" y="270" width="24" height="26" rx="3" opacity=".6"/><rect x="124" y="270" width="24" height="26" rx="3"/><rect x="252" y="270" width="24" height="26" rx="3"/><rect x="288" y="270" width="24" height="26" rx="3" opacity=".6"/></g>
    <path d="M184 332V292a16 16 0 0132 0v40z" fill="#062C29"/>
    <rect x="176" y="332" width="48" height="6" fill="#0F6E64"/><rect x="168" y="338" width="64" height="6" fill="#0F6E64"/>
    <rect x="164" y="214" width="6" height="118" fill="#0F6E64"/><rect x="230" y="214" width="6" height="118" fill="#0F6E64"/>
   </g>
   <g><rect x="38" y="296" width="6" height="44" fill="#06403B"/><circle cx="41" cy="288" r="24" fill="#17A38D" opacity=".85"/>
    <rect x="360" y="292" width="6" height="48" fill="#06403B"/><circle cx="363" cy="282" r="28" fill="#17A38D" opacity=".85"/></g>
   <rect x="0" y="338" width="400" height="120" fill="url(#tanah)"/>
  </svg>

  <div class="brand">
   <div class="brand-mark" aria-hidden="true">S</div>
   <div><div class="brand-name">Sarpras</div><div class="brand-sub">SMK Terpadu</div></div>
  </div>

  <div class="banner-isi">
   <h2>Pinjam Sarpras Sekolah, Tanpa Antre.</h2>
   <div class="ornamen" aria-hidden="true"><i></i><b></b><i></i></div>
   <p>Ajukan peminjaman proyektor, laptop, ruangan, sampai lab kejuruan, lalu pantau persetujuan dan pengembaliannya dalam satu sistem.</p>
   <div class="chips"><span class="chip">Admin</span><span class="chip">Petugas</span><span class="chip">Peminjam</span></div>
  </div>
 </section>

 <!-- Kanan: form login -->
 <section class="panel">
  <span class="eyebrow">Portal Akses Sistem</span>
  <h1>Selamat Datang</h1>
  <p class="lead">Masuk untuk mengelola atau mengajukan peminjaman sarana dan prasarana.</p>

  <?php if ($pesan !== ''): ?>
  <div class="alert" role="alert">
   <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
   <span><?= e($pesan) ?></span>
  </div>
  <?php endif; ?>

  <form action="" method="POST" id="formLogin" novalidate>
   <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
   <div class="field">
    <label for="username">Username / Nomor Telepon</label>
    <div class="input-wrap">
     <svg class="ikon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.2 3.6-7 8-7s8 2.8 8 7"/></svg>
     <input type="text" id="username" name="username" required <?= $autofokusUsername ? 'autofocus' : '' ?> autocomplete="username" maxlength="50" value="<?= e($usernameLama) ?>" placeholder="Masukkan username atau nomor telepon">
    </div>
   </div>
   <div class="field">
    <label for="password">Password</label>
    <div class="input-wrap">
     <svg class="ikon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2.5"/><path d="M8 10V7a4 4 0 018 0v3"/></svg>
     <input type="password" id="password" name="password" required <?= $fokusPassword ? 'autofocus' : '' ?> autocomplete="current-password" maxlength="100" placeholder="Masukkan password">
     <button type="button" class="lihat" id="tombolLihat" aria-label="Tampilkan password" aria-pressed="false">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
     </button>
    </div>
   </div>
   <button type="submit" class="btn" id="tombolMasuk"><span id="teksMasuk">Masuk ke Sistem</span>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
   </button>
  </form>

  <div class="demo"><b>Akun demo:</b> admin / admin123 &middot; petugas / petugas123 &middot; siswa / siswa123</div>
  <div class="footer">2026 &bull; Sarpras SMK Terpadu</div>
 </section>
</main>

<!-- Popup 1: akun tidak ditemukan -->
<div class="modal<?= $modalBelumTerdaftar ? ' tampil' : '' ?>" id="modalBelumTerdaftar" role="dialog" aria-modal="true" aria-labelledby="judulBelum" aria-describedby="isiBelum" aria-hidden="<?= $modalBelumTerdaftar ? 'false' : 'true' ?>">
 <div class="modal-kartu">
  <div class="modal-ikon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="8" r="3.6"/><path d="M3.5 20c0-3.6 2.9-6 6.5-6 1.2 0 2.3.3 3.2.7"/><circle cx="17.2" cy="16.8" r="3.3"/><path d="M19.6 19.2L22 21.6"/></svg></div>
  <span class="modal-eyebrow">Akun Tidak Ditemukan</span>
  <h2 id="judulBelum">Belum Punya Akun Sarpras</h2>
  <p id="isiBelum">Akun dengan data tersebut tidak ditemukan. Apakah kamu sudah memiliki akun peminjam Sarpras?</p>
  <?php if ($usernameLama !== ''): ?><span class="modal-nomor"><?= e($usernameLama) ?></span><?php endif; ?>
  <div class="modal-aksi">
   <button type="button" class="tombol garis" data-aksi="coba-lagi">Sudah, Coba Lagi</button>
   <button type="button" class="tombol utama" data-aksi="belum-punya">Belum Punya</button>
  </div>
 </div>
</div>

<!-- Popup 2: cara aktivasi akun -->
<div class="modal" id="modalAktivasi" role="dialog" aria-modal="true" aria-labelledby="judulAktivasi" aria-describedby="isiAktivasi" aria-hidden="true">
 <div class="modal-kartu">
  <div class="modal-ikon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V10l7-5 7 5v11"/><path d="M10 21v-6h4v6"/></svg></div>
  <span class="modal-eyebrow">Aktivasi Akun</span>
  <h2 id="judulAktivasi">Daftar Akun di Ruang Sarpras</h2>
  <p id="isiAktivasi">Akun peminjam dibuat langsung oleh petugas sekolah.</p>
  <ol class="langkah">
   <li><span class="no">1</span><span class="teks">Datang ke <b>ruang Sarpras</b> atau TU sekolah.</span></li>
   <li><span class="no">2</span><span class="teks">Temui <b>petugas</b>, sebutkan <b>nama</b>, <b>NIS/NIP</b>, dan <b>nomor telepon</b>.</span></li>
   <li><span class="no">3</span><span class="teks">Petugas <b>mendaftarkan akun</b> dan membuatkan <b>password</b>.</span></li>
   <li><span class="no">4</span><span class="teks"><b>Login di sini</b> dengan username atau nomor telepon.</span></li>
  </ol>
  <div class="modal-aksi satu"><button type="button" class="tombol utama" data-aksi="mengerti">Mengerti</button></div>
 </div>
</div>

<script>
(function(){
 var form=document.getElementById('formLogin'),pass=document.getElementById('password'),lihat=document.getElementById('tombolLihat'),
     tombol=document.getElementById('tombolMasuk'),teks=document.getElementById('teksMasuk');
 lihat.addEventListener('click',function(){
  var t=pass.type==='password';pass.type=t?'text':'password';
  lihat.setAttribute('aria-pressed',t?'true':'false');lihat.setAttribute('aria-label',t?'Sembunyikan password':'Tampilkan password');
 });
 window.addEventListener('pageshow',function(){tombol.disabled=false;teks.textContent='Masuk ke Sistem';});
 form.addEventListener('submit',function(ev){
  var u=form.username.value.trim();
  if(u===''||pass.value===''){ev.preventDefault();(u===''?form.username:pass).focus();return;}
  tombol.disabled=true;teks.textContent='Memproses...';
 });

 var mBelum=document.getElementById('modalBelumTerdaftar'),mAkt=document.getElementById('modalAktivasi'),
     inputUser=document.getElementById('username'),terbuka=null;
 function fokusAwal(m){var t=m.querySelector('.tombol.utama');if(t)t.focus();}
 function buka(m){if(terbuka)tutup(terbuka,true);m.classList.add('tampil');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-terbuka');terbuka=m;fokusAwal(m);}
 function tutup(m,tanpaFokus){m.classList.remove('tampil');m.setAttribute('aria-hidden','true');document.body.classList.remove('modal-terbuka');terbuka=null;if(!tanpaFokus){inputUser.focus();inputUser.select();}}
 if(mBelum.classList.contains('tampil')){terbuka=mBelum;document.body.classList.add('modal-terbuka');fokusAwal(mBelum);}
 document.addEventListener('click',function(ev){
  var el=ev.target.closest?ev.target.closest('[data-aksi]'):null;
  if(el){var a=el.getAttribute('data-aksi');
   if(a==='coba-lagi')tutup(mBelum);if(a==='belum-punya')buka(mAkt);if(a==='mengerti')tutup(mAkt);return;}
  if(terbuka&&ev.target===terbuka)tutup(terbuka);
 });
 document.addEventListener('keydown',function(ev){
  if(!terbuka)return;
  if(ev.key==='Escape'){tutup(terbuka);return;}
  if(ev.key==='Tab'){var f=terbuka.querySelectorAll('button'),a=f[0],z=f[f.length-1];
   if(ev.shiftKey&&document.activeElement===a){ev.preventDefault();z.focus();}
   else if(!ev.shiftKey&&document.activeElement===z){ev.preventDefault();a.focus();}}
 });
})();
</script>
</body>
</html>
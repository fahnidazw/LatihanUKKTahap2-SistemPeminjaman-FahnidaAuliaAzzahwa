<?php
// petugas/header.php - Sidebar polos (tanpa foto) + pembatasan hak akses (RBAC) + notifikasi angka
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$nama_user  = $_SESSION['u']['nama'] ?? $_SESSION['u']['nama_lengkap'] ?? 'Pengguna';
$level_user = strtolower($_SESSION['u']['level'] ?? $_SESSION['u']['role'] ?? 'petugas');
$is_admin   = ($level_user === 'admin');
$is_staff   = in_array($level_user, ['admin', 'petugas'], true);

if (!function_exists('menu_aktif')) {
    function menu_aktif($files) {
        $now = basename($_SERVER['SCRIPT_NAME']);
        return in_array($now, (array)$files, true) ? ' active' : '';
    }
}

// ---- NOTIFIKASI ANGKA (otomatis hilang saat sudah 0) ----
// Persetujuan  : jumlah pengajuan berstatus Pending (hilang setelah disetujui/ditolak)
// Pengembalian : jumlah peminjaman berstatus Disetujui yang belum dikembalikan,
//                baik alat maupun ruangan/lab (hilang setelah statusnya jadi Dikembalikan)
$n_pending = 0;
$n_belum_kembali = 0;
if ($is_staff && function_exists('q')) {
    try {
        $n_pending       = (int)(q("SELECT COUNT(*) AS c FROM peminjaman WHERE status = 'Pending'")->fetch_assoc()['c'] ?? 0);
        $n_belum_kembali = (int)(q("SELECT COUNT(*) AS c FROM peminjaman WHERE status = 'Disetujui'")->fetch_assoc()['c'] ?? 0);
    } catch (Throwable $e) {
        $n_pending = 0; $n_belum_kembali = 0;
    }
}
$n_cart = count($_SESSION['cart'] ?? []);

$item = function ($files, $icon, $label, $cnt = 0, $extra = '') {
    $href = is_array($files) ? $files[0] : $files;
    echo '<li class="menu-item' . menu_aktif($files) . $extra . '">'
       . '<a href="' . htmlspecialchars($href) . '" title="' . htmlspecialchars($label) . '">'
       . '<i class="fa-solid ' . $icon . '"></i> <span>' . htmlspecialchars($label) . '</span>'
       . ($cnt > 0 ? '<b class="cnt">' . ($cnt > 99 ? '99+' : (int)$cnt) . '</b>' : '')
       . '</a></li>';
};
?>
<style>
.sidebar{
 position:fixed;top:0;left:0;z-index:100;width:280px;height:100vh;
 display:flex;flex-direction:column;padding:26px 18px 22px;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%23FFC54D' stroke-opacity='.17' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%23FFC54D' fill-opacity='.4'/%3E%3C/svg%3E") 0 0/56px 56px,linear-gradient(165deg,#0F6258 0%,#0A413C 40%,#072B28 80%,#051D1B 100%);
 border-right:1px solid rgba(255,197,77,.35);
 box-shadow:18px 0 50px -20px rgba(6,34,31,.55);
 color:#E6F5F0;
 overflow-y:auto;overflow-x:hidden;
 scrollbar-width:none;-ms-overflow-style:none;
 isolation:isolate;
}
.sidebar::-webkit-scrollbar{width:0;height:0;display:none}
.sidebar::before{content:"";position:absolute;inset:0;z-index:-2;pointer-events:none;
 background:radial-gradient(320px 240px at 100% 0%,rgba(255,197,77,.10),transparent 65%),
            radial-gradient(300px 260px at 0% 100%,rgba(20,120,108,.22),transparent 65%),
            linear-gradient(to bottom,rgba(5,29,27,0) 0%,rgba(5,29,27,.35) 100%)}

.brand{display:flex;align-items:center;gap:12px;margin-bottom:22px;padding-bottom:18px;border-bottom:1px solid rgba(255,197,77,.25)}
.brand-mark{width:44px;height:44px;border-radius:14px;display:grid;place-items:center;font-family:var(--serif,Georgia,serif);font-weight:700;font-size:26px;color:var(--ink,#0D2B2E);
 background:linear-gradient(145deg,#F3DDA0,#E8B84A);transform:rotate(-6deg);box-shadow:0 6px 12px -6px rgba(0,0,0,.4)}
.brand-name{font-family:var(--serif,Georgia,serif);font-weight:700;font-size:20px;letter-spacing:.2em;text-transform:uppercase;color:#fff}
.brand-sub{font-size:9.5px;letter-spacing:.24em;text-transform:uppercase;color:var(--sun,#FFC54D);margin-top:2px}

.user-box{position:relative;display:flex;align-items:center;gap:12px;padding:12px 14px;margin-bottom:10px;border-radius:16px;
 background:linear-gradient(135deg,rgba(255,255,255,.13),rgba(255,255,255,.04));
 border:1px solid rgba(255,197,77,.4);backdrop-filter:blur(6px);box-shadow:inset 0 1px 0 rgba(255,255,255,.18)}
.user-avatar{flex:none;width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-weight:700;font-size:14px;color:var(--ink,#0D2B2E);
 background:linear-gradient(145deg,#F3DDA0,#E8B84A);box-shadow:0 0 0 3px rgba(255,197,77,.18)}
.user-info{min-width:0}
.user-info .u-name{font-size:13px;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}
.user-info .u-badge{display:inline-block;margin-top:3px;padding:2px 9px;border-radius:999px;font-size:9.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;
 color:var(--sun-soft,#FFE3A1);background:rgba(255,197,77,.16);border:1px solid rgba(255,197,77,.4)}

.menu-label{display:flex;align-items:center;gap:8px;margin:18px 0 8px 6px;font-size:10px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--sun,#FFC54D)}
.menu-label::before{content:"";width:5px;height:5px;background:var(--sun,#FFC54D);transform:rotate(45deg)}
.menu-list{list-style:none;display:flex;flex-direction:column;gap:4px}
.menu-item a{position:relative;display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;
 font-size:13px;font-weight:500;text-decoration:none;color:rgba(230,245,240,.88);transition:background .2s,color .2s,transform .2s}
.menu-item a i{flex:none;width:30px;height:30px;display:grid;place-items:center;border-radius:9px;font-size:13.5px;color:var(--sun,#FFC54D);
 background:rgba(255,197,77,.10);transition:.2s}
.menu-item a:hover{background:rgba(255,255,255,.10);color:#fff;transform:translateX(3px)}
.menu-item a:hover i{background:rgba(255,197,77,.22)}
.menu-item.active a{color:#fff;font-weight:700;
 background:linear-gradient(90deg,rgba(255,197,77,.18),rgba(255,197,77,.04));
 box-shadow:inset 0 0 0 1px rgba(255,197,77,.28)}
.menu-item.active a::before{content:"";position:absolute;left:-18px;top:10px;bottom:10px;width:4px;border-radius:0 4px 4px 0;background:var(--sun,#FFC54D)}
.menu-item.active a i{color:var(--ink,#0D2B2E);background:linear-gradient(145deg,#F3DDA0,#E8B84A)}
/* notifikasi angka */
.menu-item a .cnt{margin-left:auto;min-width:22px;padding:2px 7px;border-radius:999px;text-align:center;font-size:10.5px;font-weight:700;color:var(--ink,#0D2B2E);background:var(--sun,#FFC54D);box-shadow:0 0 0 3px rgba(255,197,77,.18)}
/* badge Pengembalian (barang/ruangan belum dikembalikan) diberi warna oranye agar beda dari Persetujuan (emas) */
.menu-item.notif-kembali a .cnt{background:#FF9F6B;color:#4A1A00;box-shadow:0 0 0 3px rgba(255,159,107,.22)}
.menu-item a:focus-visible{outline:2px solid var(--sun,#FFC54D);outline-offset:2px}

/* tombol keluar merah (menempel di dasar sidebar) */
.logout-wrap{margin-top:auto;padding-top:20px}
.btn-logout{display:flex;align-items:center;justify-content:center;gap:10px;padding:12px 14px;border-radius:14px;
 font-size:13.5px;font-weight:700;text-decoration:none;color:#fff;
 background:linear-gradient(135deg,#E5484D 0%,#C62828 100%);
 box-shadow:0 10px 20px -10px rgba(229,72,77,.85),inset 0 1px 0 rgba(255,255,255,.25);
 transition:transform .2s,filter .2s,box-shadow .2s}
.btn-logout:hover{transform:translateY(-1px);filter:brightness(1.1);box-shadow:0 14px 24px -10px rgba(229,72,77,.95),inset 0 1px 0 rgba(255,255,255,.3)}
.btn-logout:active{transform:translateY(0)}
.btn-logout:focus-visible{outline:2px solid #fff;outline-offset:2px}

@media(max-width:768px){
 .sidebar{width:72px;padding:18px 10px}
 .sidebar .brand{justify-content:center}
 .sidebar .brand>div:last-child,.sidebar .user-info,.sidebar .menu-label,.sidebar .menu-item span,.sidebar .menu-item .cnt{display:none}
 .sidebar .user-box{justify-content:center;padding:8px}
 .sidebar .menu-item a{justify-content:center;padding:8px}
 .sidebar .menu-item.active a::before{left:-10px}
 .sidebar .btn-logout span{display:none}
 .sidebar .btn-logout{padding:10px}
}
</style>

<nav class="sidebar" aria-label="Navigasi Utama">
 <div class="brand">
  <div class="brand-mark" aria-hidden="true">S</div>
  <div><div class="brand-name">Sarpras</div><div class="brand-sub">SMK Negeri 3 Kendal</div></div>
 </div>

 <div class="user-box">
  <div class="user-avatar"><?= htmlspecialchars(strtoupper(substr($nama_user, 0, 2))) ?></div>
  <div class="user-info">
   <div class="u-name"><?= htmlspecialchars($nama_user) ?></div>
   <span class="u-badge"><?= htmlspecialchars(strtoupper($level_user)) ?></span>
  </div>
 </div>

 <div class="menu-label">Utama</div>
 <ul class="menu-list">
  <?php $item('index.php', 'fa-house-chimney', 'Dashboard'); ?>
 </ul>

 <div class="menu-label">Peminjaman &amp; Booking</div>
 <ul class="menu-list">
  <?php
  $item('katalog-alat.php', 'fa-boxes-stacked', 'Katalog Alat & Inventaris');
  $item('booking-ruangan.php', 'fa-door-open', 'Booking Ruang & Lab');
  $item('ajukan.php', 'fa-circle-plus', 'Form Pengajuan', $n_cart);
  $item('riwayat.php', 'fa-clock-rotate-left', $is_staff ? 'Semua Riwayat' : 'Riwayat Saya');
  if ($is_staff) {
      $item('persetujuan.php', 'fa-clipboard-check', 'Persetujuan', $n_pending);
      $item('pengembalian.php', 'fa-arrow-rotate-left', 'Pengembalian & Denda', $n_belum_kembali, ' notif-kembali');
  }
  ?>
 </ul>

 <?php if ($is_admin): ?>
  <div class="menu-label">Data Master</div>
  <ul class="menu-list">
   <?php
   $item('inventaris.php', 'fa-toolbox', 'Kelola Inventaris');
   $item('users.php', 'fa-users-gear', 'Manajemen User');
   ?>
  </ul>

  <div class="menu-label">Laporan</div>
  <ul class="menu-list">
   <?php
   $item('laporan.php', 'fa-file-lines', 'Laporan Rekap');
   $item('log-aktivitas.php', 'fa-scroll', 'Log Aktivitas');
   ?>
  </ul>
 <?php endif; ?>

 <div class="logout-wrap">
  <a href="../logout.php" class="btn-logout" onclick="return confirm('Yakin ingin keluar dari sistem?')">
   <i class="fa-solid fa-right-from-bracket"></i> <span>Keluar Sistem</span>
  </a>
 </div>
</nav>
<?php
// petugas/index.php - Dashboard Sarpras Sekolah (Pilar Alat & Booking Ruang) + Hero berfoto
require __DIR__ . '/../koneksi.php';

if (empty($_SESSION['u'])) {
    go('../index.php');
}

$user       = $_SESSION['u'];
$nama_user  = $user['nama'];
$level_user = strtoupper($user['level']);

// Tanggal Indonesia
$hariID  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$bulanID = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$tanggal_lengkap = $hariID[(int)date('w')] . ', ' . date('d') . ' ' . $bulanID[(int)date('n')] . ' ' . date('Y');
$bulan_tahun     = $bulanID[(int)date('n')] . ' ' . date('Y');
$today = date('Y-m-d');

// Statistik
$total_hari_ini = q("SELECT COUNT(*) AS total FROM peminjaman WHERE tanggal_pinjam = ?", 's', $today)->fetch_assoc()['total'] ?? 0;
$total_dipinjam = q("SELECT IFNULL(SUM(d.jumlah_pinjam), 0) AS total FROM detail_peminjaman d JOIN peminjaman p ON p.id_peminjaman = d.id_peminjaman WHERE p.status = 'Disetujui'")->fetch_assoc()['total'] ?? 0;
$total_belum_kembali = q("SELECT COUNT(*) AS total FROM peminjaman WHERE status = 'Disetujui'")->fetch_assoc()['total'] ?? 0;
$total_dikembalikan = q("SELECT COUNT(*) AS total FROM peminjaman WHERE status = 'Dikembalikan'")->fetch_assoc()['total'] ?? 0;

// Transaksi Terakhir
$q_terakhir = q("SELECT p.id_peminjaman, u.nama_lengkap, b.nama_barang, d.jumlah_pinjam, p.tanggal_pinjam, p.status
                 FROM peminjaman p
                 JOIN users u ON u.id_user = p.id_user
                 JOIN detail_peminjaman d ON d.id_peminjaman = p.id_peminjaman
                 JOIN inventaris b ON b.id_barang = d.id_barang
                 ORDER BY p.id_peminjaman DESC LIMIT 5");

// Simulasi Status Ruangan Hari Ini (bisa disambungkan ke tabel ruangan / peminjaman tipe Ruang)
$ruangan_sekolah = [
    ['kode' => 'RNG-001', 'nama' => 'Aula Serbaguna', 'status' => 'terpakai', 'peminjam' => 'Pak Hendra, S.Kom', 'kegiatan' => 'Seminar Siswa RPL', 'jam' => '08:00 - 14:00'],
    ['kode' => 'RNG-002', 'nama' => 'Ruang Multimedia', 'status' => 'tersedia', 'peminjam' => '-', 'kegiatan' => '-', 'jam' => '-'],
    ['kode' => 'LAB-001', 'nama' => 'Lab Komputer RPL 1', 'status' => 'terpakai', 'peminjam' => 'Ibu Siti Aminah', 'kegiatan' => 'Praktik Web Programming', 'jam' => '07:30 - 12:00'],
    ['kode' => 'LAB-002', 'nama' => 'Lab Jaringan & Hardware', 'status' => 'pending', 'peminjam' => 'OSIS Sekolah', 'kegiatan' => 'Persiapan Lomba IT', 'jam' => '13:00 - 16:00']
];

// ---- FOTO HERO ----
// Prioritas 1: foto sendiri di asset/img/banner.jpg (atau .png/.webp) -> paling stabil, tidak butuh internet.
// Prioritas 2: foto Unsplash cadangan. Kalau satu gagal dimuat, otomatis pindah ke berikutnya.
$kandidatHero = [];
foreach (['banner.jpg', 'banner.jpeg', 'banner.png', 'banner.webp'] as $f) {
    if (is_file(__DIR__ . '/../asset/img/' . $f)) { $kandidatHero[] = '../asset/img/' . $f; break; }
}
$kandidatHero = array_merge($kandidatHero, [
    'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=1600&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1580582932707-520aed937b7b?q=80&w=1600&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=1600&auto=format&fit=crop',
    'https://images.unsplash.com/photo-1562774053-701939374585?q=80&w=1600&auto=format&fit=crop',
]);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard - Sarpras Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
// Daftar foto hero; jika satu gagal dimuat, coba yang berikutnya
var HERO_LIST = <?= json_encode($kandidatHero, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var HERO_IDX = 0;
function heroNext(img){
  HERO_IDX++;
  if (HERO_IDX < HERO_LIST.length) { img.src = HERO_LIST[HERO_IDX]; }
  else { img.style.display = 'none'; }
}
</script>
<style>
:root{
 --ink:#0D2B2E; --teal:#0F5F58; --teal-deep:#0A3A36; --sun:#FFC54D; --sun-deep:#B47A00; --sun-soft:#FFE3A1;
 --cream:#E3EDE9; --ivory:#F6FAF8; --line:#CDDFD8; --muted:#5F7A78;
 --serif:'Cormorant Garamond',Georgia,serif; --sans:'Plus Jakarta Sans',system-ui,sans-serif;
 --grad:linear-gradient(135deg,#0A4A46 0%,#0F7D70 55%,#17A38D 115%);
 --grad-hidup:linear-gradient(135deg,#0A4A46 0%,#0F6B61 55%,#177F72 120%);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{font-family:var(--sans);color:var(--ink);min-height:100vh;display:flex;overflow:hidden;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%230F5F58' stroke-opacity='.07' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%230F5F58' fill-opacity='.12'/%3E%3C/svg%3E") 0 0/56px 56px,radial-gradient(900px 500px at 12% -10%,rgba(255,197,77,.10),transparent 60%),radial-gradient(800px 520px at 100% 110%,rgba(15,107,97,.12),transparent 60%),var(--cream)}

.main-content{margin-left:280px;flex:1;height:100vh;overflow-y:auto;padding:30px 34px 40px;scrollbar-width:thin;scrollbar-color:var(--teal) transparent}
.main-content::-webkit-scrollbar{width:8px}
.main-content::-webkit-scrollbar-thumb{background:linear-gradient(var(--teal),#17A38D);border-radius:8px}

/* ===== HERO BERFOTO ===== */
.hero{position:relative;isolation:isolate;overflow:hidden;display:flex;justify-content:space-between;align-items:center;gap:24px;
 min-height:280px;padding:40px 44px;margin-bottom:26px;border-radius:26px;color:#fff;background:var(--grad-hidup);
 box-shadow:0 0 0 1px rgba(15,95,88,.25),0 34px 60px -28px rgba(10,58,54,.65)}
/* foto (img) di lapisan paling bawah + zoom pelan supaya terasa hidup */
.hero-bg-img{position:absolute;inset:0;z-index:0;width:100%;height:100%;object-fit:cover;object-position:center 40%;
 filter:saturate(1.1) contrast(1.03);transform-origin:70% 50%;animation:zoomPelan 24s ease-in-out infinite alternate}
@keyframes zoomPelan{from{transform:scale(1)}to{transform:scale(1.09)}}
/* overlay tipis: kiri gelap untuk teks, kanan dibiarkan terang agar foto terlihat jelas */
.hero::before{content:"";position:absolute;inset:0;z-index:1;pointer-events:none;
 background:linear-gradient(90deg,rgba(4,32,29,.90) 0%,rgba(6,50,45,.70) 30%,rgba(7,56,51,.28) 62%,rgba(7,56,51,.04) 100%),
            linear-gradient(to top,rgba(4,28,26,.50) 0%,transparent 45%)}
.hero-pattern{position:absolute;inset:0;z-index:2;pointer-events:none;opacity:.8;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%23FFC54D' stroke-opacity='.17' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%23FFC54D' fill-opacity='.4'/%3E%3C/svg%3E") 0 0/56px 56px;
 -webkit-mask-image:linear-gradient(100deg,#000 0%,transparent 60%);mask-image:linear-gradient(100deg,#000 0%,transparent 60%)}
.hero::after{content:"";position:absolute;inset:12px;z-index:4;border:1px solid rgba(255,197,77,.5);border-radius:18px;pointer-events:none}
.hero-left,.hero-aksi{position:relative;z-index:3}
.hero-left{max-width:62%}
.hero-left h1{font-family:var(--serif);font-weight:700;font-size:clamp(30px,3vw,42px);line-height:1.08;margin:12px 0;text-shadow:0 3px 14px rgba(0,0,0,.45)}
.hero-left p{font-size:13.5px;line-height:1.65;color:rgba(240,252,248,.96);max-width:500px;text-shadow:0 1px 8px rgba(0,0,0,.5)}
.hero-tag{display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:999px;font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--sun-soft);background:rgba(255,197,77,.16);border:1px solid rgba(255,197,77,.55);backdrop-filter:blur(6px)}
.hero-date{margin-top:14px;display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:600;color:var(--sun);background:rgba(0,0,0,.25);backdrop-filter:blur(6px)}
.hero-aksi{display:flex;flex-direction:column;gap:10px;min-width:220px}
.hero-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:13px 22px;border-radius:14px;font-weight:700;font-size:13px;text-decoration:none;color:var(--teal-deep);background:linear-gradient(145deg,#F3DDA0,#E8B84A);box-shadow:0 12px 22px -10px rgba(0,0,0,.5),inset 0 1px 0 rgba(255,255,255,.4);transition:.2s}
.hero-btn:hover{transform:translateY(-2px)}
.hero-btn.ghost{color:#fff;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.45);backdrop-filter:blur(8px);box-shadow:none}
.hero-btn.ghost:hover{background:rgba(255,255,255,.25)}

.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;margin-bottom:26px}
.stat-card{position:relative;overflow:hidden;display:flex;justify-content:space-between;align-items:center;padding:22px;border-radius:22px;background:var(--ivory);border:1px solid var(--line);box-shadow:0 14px 28px -16px rgba(15,95,88,.35)}
.stat-card::before{content:"";position:absolute;left:0;right:0;top:0;height:4px;background:var(--acc,var(--grad-hidup))}
.stat-info .label{font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--muted)}
.stat-info .value{font-family:var(--serif);font-size:36px;font-weight:700;color:var(--ink);margin:6px 0}
.stat-info .desc{font-size:11.5px;font-weight:600;color:var(--teal)}
.stat-icon{width:50px;height:50px;border-radius:16px;display:grid;place-items:center;font-size:20px;color:#fff;background:var(--acc,var(--grad-hidup));transform:rotate(-5deg)}

/* 2 PILAR UTAMA */
.section-title{font-family:var(--serif);font-size:22px;font-weight:700;margin:0 0 14px;display:flex;align-items:center;gap:10px}
.section-title::before{content:"";width:8px;height:8px;background:var(--sun);transform:rotate(45deg)}
.pilar-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:26px}
.pilar-card{background:var(--ivory);border:1px solid var(--line);border-radius:22px;padding:24px;box-shadow:0 14px 28px -16px rgba(15,95,88,.2)}
.pilar-header{display:flex;align-items:center;gap:14px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--line)}
.pilar-icon{width:46px;height:46px;border-radius:14px;background:var(--grad);color:#fff;display:grid;place-items:center;font-size:18px}
.pilar-header h4{font-family:var(--serif);font-size:18px;font-weight:700}
.pilar-header p{font-size:12px;color:var(--muted)}

/* MATRIKS STATUS RUANGAN & LAB */
.room-matrix{display:flex;flex-direction:column;gap:10px}
.room-item{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-radius:14px;background:var(--cream);border:1px solid var(--line);gap:12px}
.room-info{flex:1}
.room-name{font-size:13px;font-weight:700;color:var(--ink)}
.room-desc{font-size:11px;color:var(--muted)}
.room-badge{padding:4px 10px;border-radius:999px;font-size:10.5px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
.room-badge.tersedia{background:rgba(40,167,69,.15);color:#198754;border:1px solid rgba(40,167,69,.3)}
.room-badge.terpakai{background:rgba(220,53,69,.15);color:#dc3545;border:1px solid rgba(220,53,69,.3)}
.room-badge.pending{background:rgba(255,197,77,.2);color:#8a6100;border:1px solid rgba(255,197,77,.4)}

.grid-2{display:grid;grid-template-columns:1.4fr 1fr;gap:22px;margin-bottom:26px}
.card-box{padding:24px;border-radius:22px;background:var(--ivory);border:1px solid var(--line);box-shadow:0 14px 28px -16px rgba(15,95,88,.2)}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid var(--line)}
.card-header h3{font-family:var(--serif);font-size:20px;font-weight:700;display:flex;align-items:center;gap:10px}

.table-wrap{width:100%;overflow-x:auto}
table{width:100%;border-collapse:collapse;text-align:left;font-size:13px}
th{padding:12px 14px;font-size:10px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#fff;background:var(--grad)}
td{padding:13px 14px;border-bottom:1px solid var(--line);color:var(--ink)}
.badge{padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700}
.badge.pending{background:rgba(255,197,77,.2);color:#8a6100}
.badge.disetujui{background:rgba(15,157,138,.15);color:var(--teal)}
.badge.dikembalikan{background:rgba(40,167,69,.15);color:#198754}
.badge.ditolak{background:rgba(220,53,69,.13);color:#c92a3a}

.btn-all{display:block;width:100%;text-align:center;padding:12px;margin-top:16px;border-radius:12px;background:var(--grad);color:#fff;font-weight:700;font-size:13px;text-decoration:none}
.footer{margin-top:24px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.stats-grid,.pilar-grid,.grid-2{grid-template-columns:1fr}.hero{align-items:flex-start}.hero-left{max-width:100%}}
@media(max-width:700px){.hero{flex-direction:column;padding:30px 26px;min-height:320px}.hero-aksi{width:100%;min-width:0}.hero-btn{width:100%}.hero-left h1{font-size:30px}.hero-left p{max-width:100%}}
@media(prefers-reduced-motion:reduce){.hero-bg-img{animation:none}}
</style>
</head>
<body>

<?php include __DIR__ . '/header.php'; ?>

<main class="main-content">

 <!-- HERO BANNER BERFOTO -->
 <section class="hero">
  <img class="hero-bg-img" src="<?= e($kandidatHero[0]) ?>" alt="" onerror="heroNext(this)">
  <div class="hero-pattern"></div>
  <div class="hero-left">
   <span class="hero-tag"><i class="fa-solid fa-bolt"></i> Operasional Sarpras</span>
   <h1>Selamat Datang, <?= e($nama_user) ?>!</h1>
   <p>Sistem manajemen terpadu peminjaman alat inventaris sekolah dan matriks booking ruangan/laboratorium secara real-time.</p>
   <div class="hero-date"><i class="fa-regular fa-calendar"></i> <?= $tanggal_lengkap ?></div>
  </div>
  <div class="hero-aksi">
   <a href="ajukan.php" class="hero-btn"><i class="fa-solid fa-circle-plus"></i> Form Peminjaman</a>
   <a href="ruang.php" class="hero-btn ghost"><i class="fa-solid fa-door-open"></i> Booking Ruangan</a>
  </div>
 </section>

 <!-- STATISTIK -->
 <section class="stats-grid">
  <div class="stat-card" style="--acc:linear-gradient(90deg,#E6B94F,#D9A03C)">
   <div class="stat-info">
    <div class="label">Pinjam Hari Ini</div>
    <div class="value"><?= (int)$total_hari_ini ?></div>
    <div class="desc">pengajuan baru</div>
   </div>
   <div class="stat-icon"><i class="fa-solid fa-calendar-day"></i></div>
  </div>
  <div class="stat-card" style="--acc:linear-gradient(90deg,#0F6B61,#1F8F80)">
   <div class="stat-info">
    <div class="label">Total Dipinjam</div>
    <div class="value"><?= (int)$total_dipinjam ?></div>
    <div class="desc">unit bergerak keluar</div>
   </div>
   <div class="stat-icon"><i class="fa-solid fa-boxes-packing"></i></div>
  </div>
  <div class="stat-card" style="--acc:linear-gradient(90deg,#D98B67,#C9705A)">
   <div class="stat-info">
    <div class="label">Belum Kembali</div>
    <div class="value"><?= (int)$total_belum_kembali ?></div>
    <div class="desc">status disetujui</div>
   </div>
   <div class="stat-icon"><i class="fa-solid fa-clock-rotate-left"></i></div>
  </div>
  <div class="stat-card" style="--acc:linear-gradient(90deg,#1F8F80,#4FA88F)">
   <div class="stat-info">
    <div class="label">Dikembalikan</div>
    <div class="value"><?= (int)$total_dikembalikan ?></div>
    <div class="desc">transaksi selesai</div>
   </div>
   <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
  </div>
 </section>

 <!-- 2 PILAR UTAMA -->
 <h2 class="section-title">2 Pilar Layanan Sarpras</h2>
 <section class="pilar-grid">
  <div class="pilar-card">
   <div class="pilar-header">
    <div class="pilar-icon"><i class="fa-solid fa-laptop-code"></i></div>
    <div>
     <h4>1. Alat &amp; Inventaris Bergerak</h4>
     <p>Proyektor, Laptop, Kamera, Alat Kebersihan, dll.</p>
    </div>
   </div>
   <p style="font-size:12.5px;color:var(--muted);margin-bottom:16px">Menggunakan sistem keranjang belanja (cart) &amp; pengurangan stok otomatis saat pengajuan disetujui.</p>
   <a href="katalog.php" class="hero-btn" style="width:100%">Buka Katalog Alat <i class="fa-solid fa-arrow-right"></i></a>
  </div>

  <div class="pilar-card">
   <div class="pilar-header">
    <div class="pilar-icon" style="background:var(--sun);color:var(--ink)"><i class="fa-solid fa-door-open"></i></div>
    <div>
     <h4>2. Booking Ruang &amp; Lab</h4>
     <p>Aula, Multimedia, Lab Komputer, Ruang Rapat</p>
    </div>
   </div>
   <p style="font-size:12.5px;color:var(--muted);margin-bottom:12px">Matriks jadwal real-time: hijau (tersedia), merah (terpakai), atau kuning (pending).</p>
   <a href="ruang.php" class="hero-btn" style="width:100%;background:#fff;color:var(--teal);border:1px solid var(--teal);box-shadow:none">Cek Jadwal Ruangan <i class="fa-solid fa-arrow-right"></i></a>
  </div>
 </section>

 <!-- LIVE MATRIKS STATUS RUANGAN HARI INI -->
 <section class="card-box" style="margin-bottom:26px">
  <div class="card-header">
   <h3><i class="fa-solid fa-calendar-week"></i> Matriks Live Status Ruangan &amp; Lab Hari Ini</h3>
   <span style="font-size:11.5px;color:var(--teal);font-weight:700"><?= $tanggal_lengkap ?></span>
  </div>
  <div class="room-matrix">
   <?php foreach ($ruangan_sekolah as $r): ?>
   <div class="room-item">
    <div style="font-size:18px;color:var(--teal)"><i class="fa-solid fa-building"></i></div>
    <div class="room-info">
     <div class="room-name"><?= e($r['nama']) ?> <span style="font-size:10px;color:var(--muted)">(<?= e($r['kode']) ?>)</span></div>
     <div class="room-desc">
      <?php if ($r['status'] === 'terpakai'): ?>
       Dipakai oleh: <b><?= e($r['peminjam']) ?></b> (<?= e($r['kegiatan']) ?> | <?= e($r['jam']) ?>)
      <?php elseif ($r['status'] === 'pending'): ?>
       Menunggu konfirmasi booking dari: <b><?= e($r['peminjam']) ?></b>
      <?php else: ?>
       Ruangan kosong dan siap digunakan / dibooking.
      <?php endif; ?>
     </div>
    </div>
    <div>
     <?php if ($r['status'] === 'tersedia'): ?>
      <span class="room-badge tersedia"><i class="fa-solid fa-circle"></i> Tersedia</span>
     <?php elseif ($r['status'] === 'terpakai'): ?>
      <span class="room-badge terpakai"><i class="fa-solid fa-circle"></i> Terpakai</span>
     <?php else: ?>
      <span class="room-badge pending"><i class="fa-solid fa-circle"></i> Pending</span>
     <?php endif; ?>
    </div>
   </div>
   <?php endforeach; ?>
  </div>
 </section>

 <!-- 5 TRANSAKSI TERAKHIR -->
 <section class="card-box">
  <div class="card-header">
   <h3><i class="fa-solid fa-clock-rotate-left"></i> 5 Transaksi Peminjaman Terakhir</h3>
  </div>
  <div class="table-wrap">
   <table>
    <thead>
     <tr>
      <th>ID Struk</th>
      <th>Peminjam</th>
      <th>Sarana / Prasarana</th>
      <th>Jml</th>
      <th>Tanggal</th>
      <th>Status</th>
     </tr>
    </thead>
    <tbody>
     <?php if ($q_terakhir->num_rows > 0): ?>
         <?php while ($t = $q_terakhir->fetch_assoc()): ?>
         <tr>
          <td><b>PMJ-<?= sprintf('%04d', $t['id_peminjaman']) ?></b></td>
          <td><?= e($t['nama_lengkap']) ?></td>
          <td><?= e($t['nama_barang']) ?></td>
          <td><?= (int)$t['jumlah_pinjam'] ?></td>
          <td><?= date('d/m/Y', strtotime($t['tanggal_pinjam'])) ?></td>
          <td><span class="badge <?= e(strtolower($t['status'])) ?>"><?= e($t['status']) ?></span></td>
         </tr>
         <?php endwhile; ?>
     <?php else: ?>
         <tr><td colspan="6" style="text-align:center;color:var(--muted)">Belum ada riwayat transaksi.</td></tr>
     <?php endif; ?>
    </tbody>
   </table>
  </div>
  <a href="riwayat.php" class="btn-all">Lihat Seluruh Riwayat Peminjaman <i class="fa-solid fa-arrow-right"></i></a>
 </section>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Terpadu</div>
</main>
</body>
</html>
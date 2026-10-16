<?php
// petugas/katalog_alat.php - Katalog Alat & Inventaris Pendukung
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); }

$level_user = strtolower($_SESSION['u']['level'] ?? '');
$keyword    = trim($_GET['q'] ?? '');
$tipe_main  = trim($_GET['tipe'] ?? ''); // 'alat' atau 'inventaris'
$id_kat     = (int)($_GET['kategori'] ?? 0);

// Cari file foto di folder tempat upload disimpan (kelola-inventaris menyimpan ke /uploads/)
function foto_url($f) {
    if (empty($f)) return null;
    $f = basename($f);
    foreach (['uploads', 'asset/uploads', 'asset/img'] as $dir) {
        if (is_file(__DIR__ . '/../' . $dir . '/' . $f)) {
            return '../' . $dir . '/' . rawurlencode($f);
        }
    }
    return null;
}

// Ambil list sub-kategori berdasarkan kelompok utama yang dipilih
$q_kat = null;
if ($tipe_main === 'alat') {
    $q_kat = q("SELECT * FROM kategori WHERE id_kategori IN (1, 2, 3)");
} elseif ($tipe_main === 'inventaris') {
    $q_kat = q("SELECT * FROM kategori WHERE id_kategori IN (4, 5, 6)");
} else {
    $q_kat = q("SELECT * FROM kategori");
}

// Query utama barang
$sql = "SELECT i.*, k.nama_kategori FROM inventaris i JOIN kategori k ON k.id_kategori = i.id_kategori WHERE 1=1";
$params = [];
$types = '';

if ($tipe_main === 'alat') {
    $sql .= " AND i.id_kategori IN (1, 2, 3)";
} elseif ($tipe_main === 'inventaris') {
    $sql .= " AND i.id_kategori IN (4, 5, 6)";
}

if ($id_kat > 0) {
    $sql .= " AND i.id_kategori = ?";
    $params[] = $id_kat;
    $types .= 'i';
}

if ($keyword !== '') {
    $sql .= " AND (i.nama_barang LIKE ? OR i.kode_inventaris LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $types .= 'ss';
}

$sql .= " ORDER BY i.nama_barang ASC LIMIT 120";
$q_barang = !empty($params) ? q($sql, $types, ...$params) : q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Katalog Alat & Inventaris - Sarpras Sekolah</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
 --ink:#0D2B2E; --teal:#0F5F58; --teal-deep:#0A3A36; --sun:#FFC54D; --sun-deep:#B47A00; --sun-soft:#FFE3A1;
 --cream:#E3EDE9; --ivory:#F6FAF8; --line:#CDDFD8; --muted:#5F7A78;
 --serif:'Cormorant Garamond',Georgia,serif; --sans:'Plus Jakarta Sans',system-ui,sans-serif;
 --grad-hidup:linear-gradient(135deg,#0A4A46 0%,#0F6B61 55%,#177F72 120%);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);color:var(--ink);min-height:100vh;display:flex;overflow:hidden;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%230F5F58' stroke-opacity='.07' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%230F5F58' fill-opacity='.12'/%3E%3C/svg%3E") 0 0/56px 56px,var(--cream)}

.main-content{margin-left:280px;flex:1;height:100vh;overflow-y:auto;padding:30px 34px 40px;scrollbar-width:thin;scrollbar-color:var(--teal) transparent}
.main-content::-webkit-scrollbar{width:8px}
.main-content::-webkit-scrollbar-thumb{background:linear-gradient(var(--teal),#17A38D);border-radius:8px}

.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;background:var(--ivory);padding:20px 24px;border-radius:20px;border:1px solid var(--line);box-shadow:0 10px 25px -15px rgba(15,95,88,.2)}
.top-bar h1{font-family:var(--serif);font-size:26px;font-weight:700}
.btn-admin-add{padding:10px 18px;border-radius:12px;background:var(--sun);color:var(--teal-deep);font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 4px 12px rgba(0,0,0,.15);display:inline-flex;align-items:center;gap:8px}

.filter-wrapper{display:flex;gap:10px;align-items:center;margin-bottom:24px;background:var(--ivory);padding:16px 20px;border-radius:18px;border:1px solid var(--line);flex-wrap:wrap}
.filter-wrapper select, .filter-wrapper input{padding:10px 14px;border-radius:12px;border:1px solid var(--line);font-size:13px;background:#fff;outline:none}
.filter-wrapper button{padding:10px 18px;border-radius:12px;background:var(--grad-hidup);color:#fff;border:0;font-weight:700;cursor:pointer}

/* 3 kolom, kartu dibuat ringkas */
.catalog-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.cat-card{background:var(--ivory);border:1px solid var(--line);border-radius:16px;overflow:hidden;display:grid;grid-template-columns:168px minmax(0,1fr);box-shadow:0 10px 20px -14px rgba(15,95,88,.2);transition:.2s;text-decoration:none;color:inherit;cursor:pointer}
.cat-card:hover{transform:translateY(-3px);border-color:var(--teal);box-shadow:0 14px 26px -12px rgba(15,95,88,.35)}
/* Foto kotak: tampil utuh (tidak terpotong) */
.cat-pic{display:grid;place-items:center;padding:8px;background:#fff;border-right:1px solid var(--line)}
.cat-img{display:block;width:100%;aspect-ratio:1/1;object-fit:contain;border-radius:8px;background:#fff}
.cat-img.noimg{display:grid;place-items:center;font-size:30px;color:#7FB5AB;background:linear-gradient(135deg,#E3F1ED,#C9E3DC)}
.cat-main{display:flex;flex-direction:column;min-width:0}
.cat-body{padding:12px 14px 8px;flex:1}
.cat-top{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:6px}
.cat-tag{font-size:9.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;padding:3px 9px;border-radius:999px;background:rgba(15,95,88,.1);color:var(--teal);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:60%}
.cat-code{font-size:10.5px;color:var(--muted)}
.cat-title{font-family:var(--serif);font-size:18px;font-weight:700;line-height:1.2;margin-bottom:4px;color:var(--ink);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.cat-desc{font-size:11.5px;color:var(--muted);line-height:1.4}
.cat-footer{display:flex;justify-content:space-between;align-items:center;gap:6px;flex-wrap:wrap;padding:8px 14px;border-top:1px solid var(--line);background:rgba(255,255,255,.5)}
.cat-stock{font-size:11.5px;font-weight:700;color:var(--sun-deep)}
.click-hint{font-size:11px;font-weight:700;color:var(--teal);display:inline-flex;align-items:center;gap:5px}

.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1100px){.catalog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:480px){.cat-card{grid-template-columns:132px minmax(0,1fr)}}
@media(max-width:768px){.main-content{margin-left:72px;padding:16px}.catalog-grid{grid-template-columns:1fr}.top-bar{flex-direction:column;align-items:flex-start;gap:12px}.filter-wrapper{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1>Katalog Alat & Inventaris Pendukung</h1>
   <p style="font-size:12.5px;color:var(--muted)">Klik pada kartu barang untuk memilih dan memasukkannya ke keranjang peminjaman.</p>
  </div>
  <?php if ($level_user === 'admin'): ?>
   <a href="inventaris.php?tambah=baru" class="btn-admin-add"><i class="fa-solid fa-circle-plus"></i> Tambah Fasilitas Baru</a>
  <?php endif; ?>
 </div>

 <form method="GET" class="filter-wrapper">
  <select name="tipe" onchange="this.form.submit()">
   <option value="">-- Pilih Kelompok Utama --</option>
   <option value="alat" <?= $tipe_main === 'alat' ? 'selected' : '' ?>>1. Alat (Elektronik, Kebersihan, dll)</option>
   <option value="inventaris" <?= $tipe_main === 'inventaris' ? 'selected' : '' ?>>2. Inventaris Pendukung Ruangan</option>
  </select>

  <select name="kategori" onchange="this.form.submit()">
   <option value="">-- Pilih Sub Kategori --</option>
   <?php if($q_kat && $q_kat instanceof mysqli_result): while($k = $q_kat->fetch_assoc()): ?>
    <option value="<?= (int)$k['id_kategori'] ?>" <?= ($id_kat === (int)$k['id_kategori']) ? 'selected' : '' ?>><?= e($k['nama_kategori']) ?></option>
   <?php endwhile; endif; ?>
  </select>

  <input type="text" name="q" placeholder="Cari nama barang..." value="<?= e($keyword) ?>" style="flex:1">
  <button type="submit"><i class="fa-solid fa-search"></i> Filter</button>
 </form>

 <div class="catalog-grid">
  <?php if ($q_barang && $q_barang instanceof mysqli_result && $q_barang->num_rows > 0): ?>
      <?php while ($b = $q_barang->fetch_assoc()):
          $foto_path = foto_url($b['foto'] ?? '');
      ?>
      <!-- Seluruh card dibungkus tag <a> mengarah ke ajukan.php?aksi=tambah&id=... -->
      <a href="ajukan.php?aksi=tambah&id=<?= (int)$b['id_barang'] ?>" class="cat-card">
       <div class="cat-pic">
        <?php if ($foto_path): ?>
         <img src="<?= e($foto_path) ?>" alt="<?= e($b['nama_barang']) ?>" class="cat-img" loading="lazy">
        <?php else: ?>
         <div class="cat-img noimg" aria-hidden="true"><i class="fa-solid fa-image"></i></div>
        <?php endif; ?>
       </div>
       <div class="cat-main">
        <div class="cat-body">
         <div class="cat-top">
          <span class="cat-tag"><?= e($b['nama_kategori']) ?></span>
          <span class="cat-code"><?= e($b['kode_inventaris']) ?></span>
         </div>
         <div class="cat-title"><?= e($b['nama_barang']) ?></div>
         <div class="cat-desc">Kondisi: <b><?= e($b['kondisi']) ?></b></div>
        </div>
        <div class="cat-footer">
         <div class="cat-stock"><i class="fa-solid fa-box"></i> Stok: <b><?= (int)$b['stok'] ?> unit</b></div>
         <span class="click-hint"><i class="fa-solid fa-cart-plus"></i> Pilih <i class="fa-solid fa-arrow-right"></i></span>
        </div>
       </div>
      </a>
      <?php endwhile; ?>
  <?php else: ?>
      <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">Tidak ada data barang yang ditemukan pada filter ini.</div>
  <?php endif; ?>
 </div>
 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
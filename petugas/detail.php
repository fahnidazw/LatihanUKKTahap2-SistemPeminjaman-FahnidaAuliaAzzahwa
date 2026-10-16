<?php
// petugas/detail.php - Rincian Detail Peminjaman & Pengembalian Sarpras
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    go('persetujuan.php');
    exit;
}

// 1. Ambil data utama peminjaman
$q_main = q("SELECT * FROM peminjaman WHERE id_peminjaman = ?", 'i', $id);
if (!$q_main || $q_main->num_rows === 0) {
    echo "<script>alert('Data peminjaman dengan ID #$id tidak ditemukan di database!'); window.location='persetujuan.php';</script>";
    exit;
}
$data = $q_main->fetch_assoc();

// 2. Ambil data user peminjam
$id_user = (int)($data['id_user'] ?? 0);
$user_data = ['nama_lengkap' => 'Tidak diketahui', 'kelas_jabatan' => '-'];
if ($id_user > 0) {
    $q_usr = q("SELECT * FROM users WHERE id_user = ?", 'i', $id_user);
    if ($q_usr && $q_usr->num_rows > 0) {
        $user_data = $q_usr->fetch_assoc();
    }
}

// 3. Ambil data ruangan (jika ada)
$id_ruangan = (int)($data['id_ruangan'] ?? 0);
$ruangan_data = null;
if ($id_ruangan > 0) {
    $q_rng = q("SELECT * FROM ruangan WHERE id_ruangan = ?", 'i', $id_ruangan);
    if ($q_rng && $q_rng->num_rows > 0) {
        $ruangan_data = $q_rng->fetch_assoc();
    }
}

// 4. Ambil daftar barang detail peminjaman
$q_detail = q("SELECT dp.*, i.nama_barang, i.kode_inventaris AS kode_barang 
               FROM detail_peminjaman dp 
               JOIN inventaris i ON i.id_barang = dp.id_barang 
               WHERE dp.id_peminjaman = ?", 'i', $id);

// 5. Ambil data pengembalian & denda
$q_pg = q("SELECT * FROM pengembalian WHERE id_peminjaman = ?", 'i', $id);
$data_pg = ($q_pg && $q_pg->num_rows > 0) ? $q_pg->fetch_assoc() : null;

$is_ruangan = ($id_ruangan > 0);
$status_lower = strtolower($data['status'] ?? 'pending');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Peminjaman #STR-<?= sprintf('%04d', $id) ?> - Sarpras SMK Negeri 3 Kendal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
 --ink:#0D2B2E; --teal:#0F5F58; --teal-deep:#0A3A36; --sun:#FFC54D; --sun-deep:#B47A00; --sun-soft:#FFE3A1;
 --cream:#E3EDE9; --ivory:#F6FAF8; --line:#CDDFD8; --muted:#5F7A78; --red:#C92A3A;
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
.btn-back{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;background:var(--grad-hidup);color:#fff;border-radius:12px;font-weight:700;font-size:13px;text-decoration:none;box-shadow:0 4px 12px rgba(15,125,112,.3);transition:.2s}
.btn-back:hover{transform:translateY(-1px)}

.detail-grid{display:grid;grid-template-columns:1.6fr 1fr;gap:24px}
.card{background:var(--ivory);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 25px -15px rgba(15,95,88,.15);margin-bottom:24px}
.card h3{font-family:var(--serif);font-size:20px;color:var(--teal);margin-bottom:16px;border-bottom:1px solid var(--line);padding-bottom:10px;display:flex;align-items:center;gap:8px}

.info-group{display:grid;grid-template-columns:140px 1fr;gap:12px;margin-bottom:12px;font-size:13.5px;align-items:center}
.info-group .label{color:var(--muted);font-weight:600}
.info-group .val{color:var(--ink);font-weight:600}

table{width:100%;border-collapse:collapse;font-size:13px;margin-top:4px}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line)}
th{background:rgba(15,95,88,.05);color:var(--teal);font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.05em}

.badge{display:inline-block;padding:5px 12px;border-radius:999px;font-size:10.5px;font-weight:700;text-transform:uppercase}
.badge.pending{background:rgba(255,152,0,.2);color:#b36b00}
.badge.disetujui{background:rgba(25,135,84,.2);color:#198754}
.badge.selesai{background:rgba(13,110,253,.2);color:#0d6efd}
.badge.ditolak{background:rgba(220,53,69,.2);color:#dc3545}

.keperluan-box{background:var(--cream);padding:16px;border-radius:14px;border:1px solid var(--line);font-size:13.5px;line-height:1.6;color:var(--ink)}

.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.detail-grid{grid-template-columns:1fr}.main-content{margin-left:72px;padding:16px}}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">

 <div class="top-bar">
  <div>
   <h1>Rincian Pesanan #STR-<?= sprintf('%04d', $id) ?></h1>
   <p style="font-size:12.5px;color:var(--muted)">Informasi lengkap daftar alat, jadwal sesi, serta keperluan penggunaan fasilitas.</p>
  </div>
  <a href="persetujuan.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Kembali</a>
 </div>

 <div class="detail-grid">
  <!-- Kolom Kiri: Rincian Barang / Ruangan & Keperluan -->
  <div>
   <div class="card">
    <h3><i class="fa-solid fa-box-archive"></i> Daftar Fasilitas yang Dipinjam</h3>
    <?php if ($is_ruangan && $ruangan_data): ?>
     <div style="background:var(--cream);padding:16px;border-radius:14px;border:1px solid var(--line);">
      <b style="font-size:16px;color:var(--teal);font-family:var(--serif);"><?= e($ruangan_data['nama_ruangan']) ?></b><br>
      <span style="font-size:12.5px;color:var(--muted)">Kapasitas Ruangan: <?= e($ruangan_data['kapasitas'] ?? '-') ?> Siswa</span>
     </div>
    <?php else: ?>
     <table>
      <thead>
       <tr>
        <th>Kode Alat</th>
        <th>Nama Alat / Inventaris</th>
        <th style="text-align:center;width:100px;">Jumlah</th>
       </tr>
      </thead>
      <tbody>
       <?php if ($q_detail && $q_detail->num_rows > 0): while($dt = $q_detail->fetch_assoc()): ?>
        <tr>
         <td><code><?= e($dt['kode_barang'] ?? 'ALAT-'.$dt['id_barang']) ?></code></td>
         <td><b><?= e($dt['nama_barang']) ?></b></td>
         <td style="text-align:center;"><span style="background:rgba(15,95,88,.1);padding:4px 10px;border-radius:8px;font-weight:700;color:var(--teal);"><?= (int)$dt['jumlah_pinjam'] ?> unit</span></td>
        </tr>
       <?php endwhile; else: ?>
        <tr><td colspan="3" style="text-align:center;color:var(--muted);padding:20px;">Tidak ada rincian alat tercatat untuk transaksi ini.</td></tr>
       <?php endif; ?>
      </tbody>
     </table>
    <?php endif; ?>
   </div>

   <div class="card">
    <h3><i class="fa-solid fa-clipboard-list"></i> Keperluan &amp; Keterangan Kegiatan</h3>
    <div class="keperluan-box">
     <?= nl2br(e($data['keperluan'] ?? '-')) ?>
    </div>
   </div>
  </div>

  <!-- Kolom Kanan: Ringkasan Peminjam & Jadwal -->
  <div>
   <div class="card">
    <h3><i class="fa-solid fa-user-shield"></i> Informasi Peminjam</h3>
    <div class="info-group">
     <span class="label">Status</span>
     <span class="val"><span class="badge <?= $status_lower ?>"><?= e($data['status']) ?></span></span>
    </div>
    <div class="info-group">
     <span class="label">Nama Lengkap</span>
     <span class="val"><?= e($user_data['nama_lengkap']) ?></span>
    </div>
    <div class="info-group">
     <span class="label">Kelas / Jabatan</span>
     <span class="val"><?= e($user_data['kelas_jabatan']) ?></span>
    </div>
    <div class="info-group">
     <span class="label">Tanggal Pakai</span>
     <span class="val"><?= date('d/m/Y', strtotime($data['tanggal_pinjam'])) ?></span>
    </div>
    <div class="info-group">
     <span class="label">Jam Sesi</span>
     <span class="val"><?= e(substr($data['jam_mulai'] ?? '', 0, 5)) ?> - <?= e(substr($data['jam_selesai'] ?? '', 0, 5)) ?> WIB</span>
    </div>
   </div>

   <?php if ($status_lower === 'selesai' && $data_pg): ?>
   <div class="card" style="border-color:var(--teal)">
    <h3 style="color:var(--teal);"><i class="fa-solid fa-receipt"></i> Status Pengembalian</h3>
    <div class="info-group">
     <span class="label">Tgl Kembali</span>
     <span class="val"><?= date('d/m/Y', strtotime($data_pg['tanggal_dikembalikan'])) ?></span>
    </div>
    <div class="info-group">
     <span class="label">Total Denda</span>
     <span class="val" style="color:var(--red);">
      <?= (int)($data_pg['denda'] ?? 0) > 0 ? 'Rp ' . number_format($data_pg['denda'], 0, ',', '.') : 'Rp 0 (Tepat Waktu)' ?>
     </span>
    </div>
   </div>
   <?php endif; ?>
  </div>
 </div>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
<?php
// petugas/laporan.php - Rekap Laporan Peminjaman & Booking Sarpras
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); }

// Tangkap filter preset atau manual
$preset      = $_GET['preset'] ?? '';
$tgl_mulai   = $_GET['tgl_mulai'] ?? '';
$tgl_selesai = $_GET['tgl_selesai'] ?? '';

if ($preset === 'hari_ini') {
    $tgl_mulai   = date('Y-m-d');
    $tgl_selesai = date('Y-m-d');
} elseif ($preset === '7_hari') {
    $tgl_mulai   = date('Y-m-d', strtotime('-6 days'));
    $tgl_selesai = date('Y-m-d');
}

$sql = "SELECT p.*, u.nama_lengkap, u.nis_nip, u.kelas_jabatan, 
               i.nama_barang, r.nama_ruangan, dp.jumlah_pinjam 
        FROM peminjaman p 
        JOIN users u ON u.id_user = p.id_user 
        LEFT JOIN detail_peminjaman dp ON dp.id_peminjaman = p.id_peminjaman 
        LEFT JOIN inventaris i ON i.id_barang = dp.id_barang 
        LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan 
        WHERE 1=1";

$params = [];
$types  = '';

if (!empty($tgl_mulai) && !empty($tgl_selesai)) {
    $sql .= " AND p.tanggal_pinjam BETWEEN ? AND ?";
    $params[] = $tgl_mulai;
    $params[] = $tgl_selesai;
    $types  .= 'ss';
}

$sql .= " ORDER BY p.id_peminjaman DESC";
$q_laporan = !empty($params) ? q($sql, $types, ...$params) : q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Laporan Rekap Sarpras - SMK Negeri 3 Kendal</title>
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

.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;background:var(--ivory);padding:20px 24px;border-radius:20px;border:1px solid var(--line);box-shadow:0 10px 25px -15px rgba(15,95,88,.2)}
.top-bar h1{font-family:var(--serif);font-size:26px;font-weight:700}

.filter-box{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:24px;background:var(--ivory);padding:16px 20px;border-radius:16px;border:1px solid var(--line)}
.filter-box input{padding:8px 12px;border-radius:8px;border:1px solid var(--line);font-size:12.5px;background:#fff;outline:none}
.btn-filter{padding:8px 14px;border-radius:8px;background:var(--teal);color:#fff;border:0;font-weight:700;font-size:12px;cursor:pointer}
.btn-preset{padding:8px 12px;border-radius:8px;background:var(--cream);color:var(--teal);border:1px solid var(--line);font-weight:700;font-size:12px;cursor:pointer;text-decoration:none}
.btn-preset:hover{background:var(--line)}
.btn-print{margin-left:auto;padding:8px 16px;border-radius:8px;background:var(--sun-deep);color:#fff;border:0;font-weight:700;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}

.table-card{background:var(--ivory);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 25px -15px rgba(15,95,88,.15)}
table{width:100%;border-collapse:collapse;font-size:12.5px;table-layout:fixed}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle;word-wrap:break-word}
th{font-weight:700;color:var(--teal);text-transform:uppercase;font-size:10.5px;letter-spacing:.06em;background:rgba(15,95,88,.05)}

th:nth-child(1), td:nth-child(1){width:9%}
th:nth-child(2), td:nth-child(2){width:17%}
th:nth-child(3), td:nth-child(3){width:19%}
th:nth-child(4), td:nth-child(4){width:7%}
th:nth-child(5), td:nth-child(5){width:11%}
th:nth-child(6), td:nth-child(6){width:24%}
th:nth-child(7), td:nth-child(7){width:13%}

/* 4 Jenis Badge Status */
.badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:9.5px;font-weight:700;text-transform:uppercase}
.badge.pending{background:rgba(255,152,0,.2);color:#b36b00}      /* 1. Pending */
.badge.disetujui{background:rgba(25,135,84,.2);color:#198754}     /* 2. Disetujui */
.badge.ditolak{background:rgba(220,53,69,.2);color:#dc3545}      /* 3. Ditolak */
.badge.dikembalikan{background:rgba(13,110,253,.2);color:#0d6efd} /* 4. Dikembalikan */

.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.main-content{margin-left:72px;padding:16px}}
@media print{
 body{background:#fff}
 .main-content{margin-left:0;padding:0;height:auto;overflow:visible}
 .top-bar, .filter-box, .footer, .sidebar{display:none !important}
 .table-card{box-shadow:none;border:0;padding:0}
}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1>Laporan Rekapitulasi Sarpras</h1>
   <p style="font-size:12.5px;color:var(--muted)">Arsip riwayat lengkap peminjaman alat dan booking ruangan sekolah.</p>
  </div>
 </div>

 <form method="GET" class="filter-box">
  <span style="font-size:12px;font-weight:700;color:var(--teal)"><i class="fa-solid fa-filter"></i> Periode:</span>
  <input type="date" name="tgl_mulai" value="<?= htmlspecialchars($tgl_mulai) ?>">
  <span>s.d</span>
  <input type="date" name="tgl_selesai" value="<?= htmlspecialchars($tgl_selesai) ?>">
  <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i> Filter</button>
  
  <a href="laporan.php?preset=hari_ini" class="btn-preset"><i class="fa-solid fa-calendar-day"></i> Hari Ini</a>
  <a href="laporan.php?preset=7_hari" class="btn-preset"><i class="fa-solid fa-calendar-week"></i> 7 Hari Terakhir</a>

  <button type="button" onclick="window.print()" class="btn-print"><i class="fa-solid fa-print"></i> Cetak Laporan</button>
 </form>

 <div class="table-card">
  <table>
   <thead>
    <tr>
     <th>ID</th>
     <th>Peminjam</th>
     <th>Fasilitas</th>
     <th>Jml</th>
     <th>Tanggal</th>
     <th>Keperluan</th>
     <th>Status</th>
    </tr>
   </thead>
   <tbody>
    <?php if ($q_laporan && $q_laporan instanceof mysqli_result && $q_laporan->num_rows > 0): ?>
        <?php while ($row = $q_laporan->fetch_assoc()): 
            $fasilitas = !empty($row['nama_barang']) ? '<i class="fa-solid fa-box"></i> ' . e($row['nama_barang']) : '<i class="fa-solid fa-door-open" style="color:var(--teal)"></i> ' . e($row['nama_ruangan']);
            
            $status_raw = strtolower($row['status']);
            // Petakan database 'selesai' ke label 'Dikembalikan'
            if ($status_raw === 'selesai' || $status_raw === 'dikembalikan') {
                $status_class = 'dikembalikan';
                $status_label = 'Dikembalikan';
            } elseif ($status_raw === 'disetujui') {
                $status_class = 'disetujui';
                $status_label = 'Disetujui';
            } elseif ($status_raw === 'ditolak') {
                $status_class = 'ditolak';
                $status_label = 'Ditolak';
            } else {
                $status_class = 'pending';
                $status_label = 'Pending';
            }
        ?>
        <tr>
         <td><b>#STR-<?= sprintf('%04d', $row['id_peminjaman']) ?></b></td>
         <td>
          <b><?= e($row['nama_lengkap']) ?></b><br>
          <span style="font-size:10.5px;color:var(--muted)"><?= e($row['kelas_jabatan']) ?></span>
         </td>
         <td><?= $fasilitas ?></td>
         <td><?= $row['jumlah_pinjam'] > 0 ? $row['jumlah_pinjam'] . ' unit' : '-' ?></td>
         <td><?= e($row['tanggal_pinjam']) ?></td>
         <td style="font-size:11.5px"><?= e($row['keperluan']) ?></td>
         <td><span class="badge <?= $status_class ?>"><?= e($status_label) ?></span></td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
         <td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data rekapitulasi laporan dalam periode ini.</td>
        </tr>
    <?php endif; ?>
   </tbody>
  </table>
 </div>
 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
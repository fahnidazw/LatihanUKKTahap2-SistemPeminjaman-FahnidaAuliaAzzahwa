<?php
// petugas/persetujuan.php - Daftar Persetujuan Peminjaman & Booking Ruangan
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); }

// Aksi Ubah Status (Setujui / Tolak)
if (isset($_GET['aksi'], $_GET['id'])) {
    $id         = (int)$_GET['id'];
    $aksi       = $_GET['aksi'];
    $id_petugas = (int)($_SESSION['u']['id_user'] ?? 0);
    $kode       = '#STR-' . sprintf('%04d', $id);

    if (in_array($aksi, ['setuju', 'tolak'], true)) {
        $db->begin_transaction();

        // Kunci baris & pastikan masih Pending (cegah stok berkurang dua kali saat refresh)
        $r   = q("SELECT status FROM peminjaman WHERE id_peminjaman = ? FOR UPDATE", 'i', $id);
        $cur = $r ? $r->fetch_assoc() : null;
        if (!$cur || $cur['status'] !== 'Pending') {
            $db->rollback();
            $_SESSION['flash_pesan'] = 'Pengajuan ini sudah diproses sebelumnya.';
            go('persetujuan.php');
        }

        $ok = true; $pesan = '';
        if ($aksi === 'setuju') {
            $r    = q("SELECT dp.id_barang, dp.jumlah_pinjam, b.stok, b.nama_barang
                       FROM detail_peminjaman dp JOIN inventaris b ON b.id_barang = dp.id_barang
                       WHERE dp.id_peminjaman = ? FOR UPDATE", 'i', $id);
            $list = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];

            // Cek SEMUA stok dulu sebelum ada yang dikurangi
            foreach ($list as $it) {
                if ((int)$it['stok'] < (int)$it['jumlah_pinjam']) {
                    $ok = false;
                    $pesan = 'Stok ' . $it['nama_barang'] . ' tidak mencukupi untuk disetujui!';
                    break;
                }
            }
            // Kurangi stok seluruh barang
            if ($ok) foreach ($list as $it) {
                if (!q("UPDATE inventaris SET stok = stok - ? WHERE id_barang = ?", 'ii', (int)$it['jumlah_pinjam'], (int)$it['id_barang'])) {
                    $ok = false; break;
                }
            }
        }

        if ($ok) {
            $status_baru = ($aksi === 'setuju') ? 'Disetujui' : 'Ditolak';
            $ok = (bool)q("UPDATE peminjaman SET status = ?, id_petugas = ? WHERE id_peminjaman = ?", 'sii', $status_baru, $id_petugas, $id);
        }

        if ($ok) {
            $db->commit();
            // Catat ke log aktivitas (muncul di filter Setujui / Tolak)
            logact(($aksi === 'setuju' ? 'Menyetujui' : 'Menolak') . " peminjaman $kode");
        } else {
            $db->rollback();
            $_SESSION['flash_pesan'] = $pesan ?: 'Gagal memproses pengajuan.';
        }
    }
    go('persetujuan.php');
}

// Pesan sekali tampil
$flash_pesan = $_SESSION['flash_pesan'] ?? '';
unset($_SESSION['flash_pesan']);

// Ambil data pengajuan
$sql = "SELECT p.*, u.nama_lengkap, u.nis_nip, u.kelas_jabatan, r.nama_ruangan,
               SUM(dp.jumlah_pinjam) AS total_jumlah
        FROM peminjaman p
        JOIN users u ON u.id_user = p.id_user
        LEFT JOIN detail_peminjaman dp ON dp.id_peminjaman = p.id_peminjaman
        LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan
        GROUP BY p.id_peminjaman
        ORDER BY p.id_peminjaman DESC";
$q_list = q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Persetujuan Peminjaman & Booking - Sarpras</title>
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

.flash{background:#FFE0DC;color:#A3281A;padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:600;font-size:13px}

.table-card{background:var(--ivory);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 25px -15px rgba(15,95,88,.15)}
table{width:100%;border-collapse:collapse;font-size:12.5px;table-layout:fixed}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle;word-wrap:break-word}
th{font-weight:700;color:var(--teal);text-transform:uppercase;font-size:10.5px;letter-spacing:.06em;background:rgba(15,95,88,.05)}

/* Lebar kolom proporsional & rapi */
th:nth-child(1), td:nth-child(1){width:10%} /* ID */
th:nth-child(2), td:nth-child(2){width:18%} /* Peminjam */
th:nth-child(3), td:nth-child(3){width:16%} /* Kelas Peminjam */
th:nth-child(4), td:nth-child(4){width:8%}  /* Jml */
th:nth-child(5), td:nth-child(5){width:12%} /* Tgl Pakai */
th:nth-child(6), td:nth-child(6){width:11%} /* Status */
th:nth-child(7), td:nth-child(7){width:15%} /* Aksi (Ikon Sejajar) */

.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:9.5px;font-weight:700;text-transform:uppercase}
.badge.pending{background:rgba(255,152,0,.2);color:#b36b00}
.badge.disetujui{background:rgba(25,135,84,.2);color:#198754}
.badge.ditolak{background:rgba(220,53,69,.2);color:#dc3545}
.badge.dikembalikan{background:rgba(13,110,253,.15);color:#0b5ed7}

/* Tombol Aksi Sejajar Berbentuk Ikon Seragam */
.action-container{display:flex;flex-direction:row;gap:6px;align-items:center}
.btn-action{width:32px;height:32px;border-radius:8px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.1);transition:.15s;border:0;cursor:pointer}
.btn-action:hover{transform:translateY(-1px)}
.btn-detail{background:var(--cream);color:var(--teal);border:1px solid var(--line)}
.btn-setuju{background:#198754;color:#fff}
.btn-tolak{background:#dc3545;color:#fff}

.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.main-content{margin-left:72px;padding:16px}}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1>Daftar Persetujuan Peminjaman & Booking</h1>
   <p style="font-size:12.5px;color:var(--muted)">Validasi permohonan peminjaman alat dan booking ruangan dari warga sekolah.</p>
  </div>
 </div>

 <?php if ($flash_pesan !== ''): ?>
  <div class="flash" role="alert"><?= e($flash_pesan) ?></div>
 <?php endif; ?>

 <div class="table-card">
  <table>
   <thead>
    <tr>
     <th>ID</th>
     <th>Peminjam</th>
     <th>Kelas Peminjam</th>
     <th>Jml</th>
     <th>Tgl Pakai</th>
     <th>Status</th>
     <th>Aksi</th>
    </tr>
   </thead>
   <tbody>
    <?php if ($q_list && $q_list instanceof mysqli_result && $q_list->num_rows > 0): ?>
        <?php while ($row = $q_list->fetch_assoc()):
            $status_class = strtolower($row['status']);
        ?>
        <tr>
         <td><b>#STR-<?= sprintf('%04d', $row['id_peminjaman']) ?></b></td>
         <td><b><?= e($row['nama_lengkap']) ?></b></td>
         <td><?= e($row['kelas_jabatan']) ?></td>
         <td><?= $row['total_jumlah'] > 0 ? $row['total_jumlah'] . ' unit' : (!empty($row['nama_ruangan']) ? 'Ruangan' : '-') ?></td>
         <td><?= e($row['tanggal_pinjam']) ?></td>
         <td><span class="badge <?= e($status_class) ?>"><?= e($row['status']) ?></span></td>
         <td>
          <div class="action-container">
           <!-- Tombol Detail -->
           <a href="detail.php?id=<?= (int)$row['id_peminjaman'] ?>" class="btn-action btn-detail" title="Lihat Detail Pesanan"><i class="fa-solid fa-eye"></i></a>

           <?php if ($row['status'] === 'Pending'): ?>
            <a href="persetujuan.php?aksi=setuju&id=<?= (int)$row['id_peminjaman'] ?>" class="btn-action btn-setuju" onclick="return confirm('Setujui pengajuan ini? Stok seluruh barang akan otomatis berkurang.')" title="Setujui"><i class="fa-solid fa-check"></i></a>
            <a href="persetujuan.php?aksi=tolak&id=<?= (int)$row['id_peminjaman'] ?>" class="btn-action btn-tolak" onclick="return confirm('Tolak pengajuan ini?')" title="Tolak"><i class="fa-solid fa-xmark"></i></a>
           <?php endif; ?>
          </div>
         </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
         <td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data pengajuan peminjaman atau booking ruangan.</td>
        </tr>
    <?php endif; ?>
   </tbody>
  </table>
 </div>
 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
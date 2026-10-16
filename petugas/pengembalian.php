<?php
// petugas/pengembalian.php - Manajemen Pengembalian Sarpras & Pencatatan Denda
// Hak akses: hanya ADMIN dan PETUGAS. Peminjam tidak boleh membuka halaman ini.
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); exit; }

$level_login = strtolower($_SESSION['u']['level'] ?? $_SESSION['u']['role'] ?? '');
if (!in_array($level_login, ['admin', 'petugas'], true)) {
    go('index.php'); // peminjam dialihkan ke dashboard
    exit;
}

// Proses Pengembalian Barang & Denda
if (isset($_GET['aksi'], $_GET['id']) &&$_GET['aksi'] === 'proses') {
    $id_peminjaman = (int)$_GET['id'];
    $id_petugas    = (int)($_SESSION['u']['id_user'] ?? $_SESSION['u']['id'] ?? 0);$denda_input   = max(0, (int)($_GET['denda'] ?? 0));$tgl_sekarang  = date('Y-m-d');

    // Hanya proses jika masih berstatus Disetujui (mencegah stok bertambah dua kali saat halaman di-refresh)
    $cek = q("SELECT status FROM peminjaman WHERE id_peminjaman = ?", 'i', $id_peminjaman)->fetch_assoc();
    if ($cek &&$cek['status'] === 'Disetujui') {

        // 1. Update status peminjaman langsung ke Selesai
        q("UPDATE peminjaman SET status = 'Selesai', id_petugas = ? WHERE id_peminjaman = ?",
          'ii', $id_petugas,$id_peminjaman);

        // 2. Masukkan atau perbarui data ke tabel pengembalian (mencegah error duplikat id_peminjaman)
        q("INSERT INTO pengembalian (id_peminjaman, tanggal_dikembalikan, kondisi_pulang, denda)
           VALUES (?, ?, 'Baik', ?)
           ON DUPLICATE KEY UPDATE tanggal_dikembalikan = VALUES(tanggal_dikembalikan), denda = VALUES(denda), kondisi_pulang = 'Baik'",
          'isi', $id_peminjaman, $tgl_sekarang,$denda_input);

        // 3. Ambil data detail barang untuk mengembalikan stok inventaris
        $q_det = q("SELECT id_barang, jumlah_pinjam FROM detail_peminjaman WHERE id_peminjaman = ?", 'i', $id_peminjaman);
        if ($q_det &&$q_det->num_rows > 0) {
            while ($dt =$q_det->fetch_assoc()) {
                $id_brg = (int)$dt['id_barang'];
                $jml    = (int)$dt['jumlah_pinjam'];
                if ($id_brg > 0 &&$jml > 0) {
                    q("UPDATE inventaris SET stok = stok + ? WHERE id_barang = ?", 'ii', $jml,$id_brg);
                }
            }
        }

        // 4. Catat log aktivitas
        try {
            q("INSERT INTO log_aktivitas (id_user, aksi) VALUES (?, ?)",
              'is', $id_petugas, "Memproses pengembalian peminjaman ID #PMJ-" . sprintf('%04d', $id_peminjaman));
        } catch (Throwable $e) {}
    }

    // Alihkan kembali ke halaman pengembalian
    go('pengembalian.php');
    exit;
}

// Ambil daftar peminjaman aktif dan selesai beserta data denda dan total jumlah barang
$sql = "SELECT p.*, u.nama_lengkap, u.nis_nip, u.kelas_jabatan, r.nama_ruangan,
               SUM(dp.jumlah_pinjam) AS total_jumlah,
               COALESCE(pg.denda, 0) AS denda_tercatat, pg.tanggal_dikembalikan
        FROM peminjaman p 
        JOIN users u ON u.id_user = p.id_user 
        LEFT JOIN detail_peminjaman dp ON dp.id_peminjaman = p.id_peminjaman 
        LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan 
        LEFT JOIN pengembalian pg ON pg.id_peminjaman = p.id_peminjaman
        WHERE p.status IN ('Disetujui', 'Selesai')
        GROUP BY p.id_peminjaman
        ORDER BY p.id_peminjaman DESC";
$q_list = q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pengelolaan Pengembalian Sarpras - SMK Negeri 3 Kendal</title>
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

.table-card{background:var(--ivory);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 25px -15px rgba(15,95,88,.15)}
table{width:100%;border-collapse:collapse;font-size:12.5px;table-layout:fixed}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle;word-wrap:break-word}
th{font-weight:700;color:var(--teal);text-transform:uppercase;font-size:10.5px;letter-spacing:.06em;background:rgba(15,95,88,.05)}

/* Lebar kolom proporsional & rapi */
th:nth-child(1), td:nth-child(1){width:9%}  /* ID */
th:nth-child(2), td:nth-child(2){width:17%} /* Peminjam */
th:nth-child(3), td:nth-child(3){width:15%} /* Kelas Peminjam */
th:nth-child(4), td:nth-child(4){width:8%}  /* Jml */
th:nth-child(5), td:nth-child(5){width:14%} /* Tgl Rencana Selesai */
th:nth-child(6), td:nth-child(6){width:11%} /* Status */
th:nth-child(7), td:nth-child(7){width:26%} /* Aksi (Ikon Sejajar) */

.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:9.5px;font-weight:700;text-transform:uppercase}
.badge.disetujui{background:rgba(25,135,84,.2);color:#198754}
.badge.selesai{background:rgba(13,110,253,.2);color:#0d6efd}

/* Tombol Aksi Sejajar Berbentuk Ikon Seragam */
.action-container{display:flex;flex-direction:row;gap:6px;align-items:center}
.btn-action{width:32px;height:32px;border-radius:8px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.1);transition:.15s;border:0;cursor:pointer}
.btn-action:hover{transform:translateY(-1px)}
.btn-detail{background:var(--cream);color:var(--teal);border:1px solid var(--line)}
.btn-kembali{background:var(--grad-hidup);color:#fff}
.btn-cetak{background:#2b8a3e;color:#fff}

/* MODAL POP-UP DENDA */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,42,39,0.6);backdrop-filter:blur(4px);z-index:1000;place-items:center;padding:20px}
.modal-overlay.active{display:grid}
.modal-card{background:var(--ivory);border:1px solid var(--line);border-radius:24px;width:100%;max-width:440px;padding:28px;box-shadow:0 20px 40px rgba(0,0,0,.3)}
.modal-header h3{font-family:var(--serif);font-size:22px;font-weight:700;color:var(--teal);margin-bottom:8px}
.modal-body{font-size:13.5px;line-height:1.6;margin-bottom:20px;color:var(--ink)}
.modal-footer{display:flex;gap:10px}
.btn-modal-ok{flex:1;padding:12px;border-radius:12px;background:var(--grad-hidup);color:#fff;border:0;font-weight:700;cursor:pointer;text-align:center;text-decoration:none}
.btn-modal-batal{flex:1;padding:12px;border-radius:12px;background:var(--cream);color:var(--teal);border:1px solid var(--line);font-weight:700;cursor:pointer;text-align:center;text-decoration:none}

.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.main-content{margin-left:72px;padding:16px}}
</style>
<script>
function cekDanProsesPengembalian(idPeminjaman, tglRencana, jamSelesai) {
    const sekarang = new Date();
    const batasWaktuStr = tglRencana + 'T' + (jamSelesai ? jamSelesai : '23:59:59');
    const batasWaktu = new Date(batasWaktuStr);

    let denda = 0;
    let pesanKeterlambatan = "Peminjaman dikembalikan tepat waktu. Tidak ada denda.";

    if (sekarang > batasWaktu) {
        const selisihMs = sekarang - batasWaktu;
        const selisihJamTotal = Math.ceil(selisihMs / (1000 * 60 * 60));
        const selisihHari = Math.floor(selisihJamTotal / 24);

        if (selisihHari > 0) {
            denda = selisihHari * 5000;
            const sisaJam = selisihJamTotal % 24;
            if (sisaJam > 0) { denda += (sisaJam * 500); }
            pesanKeterlambatan = `<b>PERINGATAN: Keterlambatan Terdeteksi!</b><br>Terlambat sekitar <b>${selisihHari} hari</b> (total ${selisihJamTotal} jam).<br>Total Denda Dikenakan: <b style="color:var(--red); font-size:16px;">Rp ${denda.toLocaleString('id-ID')}</b>`;
        } else {
            denda = selisihJamTotal * 500;
            pesanKeterlambatan = `<b>PERINGATAN: Keterlambatan Terdeteksi!</b><br>Terlambat selama <b>${selisihJamTotal} jam</b> di hari yang sama.<br>Total Denda Dikenakan: <b style="color:var(--red); font-size:16px;">Rp ${denda.toLocaleString('id-ID')}</b>`;
        }
    } else {
        pesanKeterlambatan = `<b>Status: Tepat Waktu</b><br>Fasilitas dikembalikan sebelum atau sesuai jadwal batas waktu. Tidak ada denda.`;
    }

    document.getElementById('modalBodyText').innerHTML = pesanKeterlambatan;
    document.getElementById('btnKonfirmasiReturn').href = `pengembalian.php?aksi=proses&id=${idPeminjaman}&denda=${denda}`;
    document.getElementById('modalDenda').classList.add('active');
}

function tutupModalDenda() {
    document.getElementById('modalDenda').classList.remove('active');
}
</script>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1>Pengelolaan Pengembalian Sarpras</h1>
   <p style="font-size:12.5px;color:var(--muted)">Catat pengembalian alat dan fasilitas sekolah dengan deteksi denda otomatis.</p>
  </div>
 </div>

 <div class="table-card">
  <table>
   <thead>
    <tr>
     <th>ID</th>
     <th>Peminjam</th>
     <th>Kelas Peminjam</th>
     <th>Jml</th>
     <th>Jadwal Selesai</th>
     <th>Status</th>
     <th>Aksi</th>
    </tr>
   </thead>
   <tbody>
    <?php if ($q_list && $q_list instanceof mysqli_result &&$q_list->num_rows > 0): ?>
        <?php while ($row = $q_list->fetch_assoc()):$status_class = strtolower($row['status']);$tgl_rencana  = $row['tanggal_kembali_rencana'] ?? $row['tanggal_pinjam'];
            $jam_selesai  =$row['jam_selesai'] ?? '23:59:00';
            $denda_db     = (int)($row['denda_tercatat'] ?? 0);
        ?>
        <tr>
         <td><b>#STR-<?= sprintf('%04d', $row['id_peminjaman']) ?></b></td>
         <td><b><?= e($row['nama_lengkap']) ?></b></td>
         <td><?= e($row['kelas_jabatan']) ?></td>
         <td><?= $row['total_jumlah'] > 0 ? $row['total_jumlah'] . ' unit' : (!empty($row['nama_ruangan']) ? 'Ruangan' : '-') ?></td>
         <td>
          <?= e($tgl_rencana) ?><br>
          <span style="font-size:10.5px;color:var(--muted)"><?= e(substr($jam_selesai, 0, 5)) ?> WIB</span>
         </td>
         <td>
          <span class="badge <?= $status_class ?>"><?= e($row['status']) ?></span>
          <?php if ($denda_db > 0): ?>
           <br><span style="font-size:10.5px;color:var(--red);font-weight:700">Denda: Rp <?= number_format($denda_db, 0, ',', '.') ?></span>
          <?php endif; ?>
         </td>
         <td>
          <div class="action-container">
           <!-- Tombol Detail -->
           <a href="detail.php?id=<?= $row['id_peminjaman'] ?>" class="btn-action btn-detail" title="Lihat Detail Pesanan"><i class="fa-solid fa-eye"></i></a>

           <!-- Tombol Cetak Laporan Peminjaman -->
           <a href="cetak-struk.php?cetak=1&id=<?= $row['id_peminjaman'] ?>" target="_blank" class="btn-action btn-cetak" title="Cetak Laporan Peminjaman"><i class="fa-solid fa-print"></i></a>

           <?php if ($row['status'] === 'Disetujui'): ?>
            <button type="button" class="btn-action btn-kembali" onclick="cekDanProsesPengembalian(<?= $row['id_peminjaman'] ?>, '<?= $tgl_rencana ?>', '<?= $jam_selesai ?>')" title="Proses Pengembalian"><i class="fa-solid fa-rotate-left"></i></button>
           <?php endif; ?>
          </div>
         </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr>
         <td colspan="7" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data peminjaman aktif yang perlu dikembalikan.</td>
        </tr>
    <?php endif; ?>
   </tbody>
  </table>
 </div>

 <!-- MODAL POP-UP PERINGATAN DENDA & KETERLAMBATAN -->
 <div class="modal-overlay" id="modalDenda">
  <div class="modal-card">
   <div class="modal-header">
    <h3>Konfirmasi Pengembalian</h3>
   </div>
   <div class="modal-body" id="modalBodyText">
    <!-- Konten dinamis dari JavaScript -->
   </div>
   <div class="modal-footer">
    <button type="button" class="btn-modal-batal" onclick="tutupModalDenda()">Batal</button>
    <a href="#" id="btnKonfirmasiReturn" class="btn-modal-ok">Proses Selesai</a>
   </div>
  </div>
 </div>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
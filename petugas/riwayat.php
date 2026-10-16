<?php
// petugas/riwayat.php - Riwayat Peminjaman Alat & Booking Ruangan
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); exit; }

$uid     = (int)($_SESSION['u']['id_user'] ?? $_SESSION['u']['id'] ?? 0);
$level     = strtolower($_SESSION['u']['level'] ?? $_SESSION['u']['role'] ?? '');
$is_staff  = in_array($level, ['admin', 'petugas'], true);
$own       = $is_staff ? '' : ' AND p.id_user = ' . $uid;

if (!function_exists('qx')) {
    function qx($sql, $types = '', $params = []) { return $params ? q($sql, $types, ...$params) : q($sql); }
}

if (!function_exists('badge')) {
    function badge($s) { return '<span class="badge ' . e(strtolower($s)) . '">' . e($s) . '</span>'; }
}

// ---------- Batalkan pengajuan (hanya milik sendiri & masih Pending) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'batal') {
    $id = (int)($_POST['id'] ?? 0);
    try {
        $row = q("SELECT id_peminjaman FROM peminjaman WHERE id_peminjaman = ? AND id_user = ? AND status = 'Pending'", 'ii', $id, $uid)->fetch_assoc();
        if ($row) {
            q("DELETE FROM detail_peminjaman WHERE id_peminjaman = ?", 'i', $id);
            q("DELETE FROM peminjaman WHERE id_peminjaman = ? AND id_user = ? AND status = 'Pending'", 'ii', $id, $uid);
            try { q("INSERT INTO log_aktivitas (id_user, aksi) VALUES (?, ?)", 'is', $uid, "Membatalkan pengajuan PMJ-" . sprintf('%04d', $id)); } catch (Throwable $e) {}
            $_SESSION['flash_riwayat'] = ['ok', 'Pengajuan berhasil dibatalkan.'];
        } else {
            $_SESSION['flash_riwayat'] = ['err', 'Pengajuan tidak ditemukan atau sudah diproses petugas.'];
        }
    } catch (Throwable $e) {
        $_SESSION['flash_riwayat'] = ['err', 'Gagal membatalkan pengajuan.'];
    }
    header('Location: riwayat.php'); exit;
}
$flash = $_SESSION['flash_riwayat'] ?? null; unset($_SESSION['flash_riwayat']);

// ---------- Filter ----------
$daftar_status = ['Pending', 'Disetujui', 'Dikembalikan', 'Ditolak', 'Selesai'];
$status = $_GET['status'] ?? '';
$jenis  = $_GET['jenis']  ?? '';
$cari   = trim($_GET['cari'] ?? '');
$hal    = max(1, (int)($_GET['hal'] ?? 1));
$perHal = 10;

// Base FROM & WHERE
$from  = "FROM peminjaman p 
          JOIN users u ON u.id_user = p.id_user 
          LEFT JOIN ruangan r ON r.id_ruangan = p.id_ruangan
          LEFT JOIN pengembalian pg ON pg.id_peminjaman = p.id_peminjaman";
$where = "WHERE 1=1" . $own; $tp = ''; $pr = [];

if (in_array($status, $daftar_status, true)) { $where .= " AND p.status = ?"; $tp .= 's'; $pr[] = $status; } else { $status = ''; }
if ($jenis === 'alat')       { $where .= " AND COALESCE(p.id_ruangan,0) = 0"; }
elseif ($jenis === 'ruangan')  { $where .= " AND COALESCE(p.id_ruangan,0) > 0"; }
else { $jenis = ''; }

// Pencarian berdasarkan ID Struk / Angka ID
if ($cari !== '') {
    // Bersihkan prefix STR- atau PMJ- jika diketik user
    $cari_id = (int)preg_replace('/[^0-9]/', '', $cari);
    if ($cari_id > 0) {
        $where .= " AND (p.id_peminjaman = ? OR p.keperluan LIKE ? OR u.nama_lengkap LIKE ?)";
        $tp .= 'iss'; array_push($pr, $cari_id, "%$cari%", "%$cari%");
    } else {
        $where .= " AND (p.keperluan LIKE ? OR u.nama_lengkap LIKE ?)";
        $tp .= 'ss'; array_push($pr, "%$cari%", "%$cari%");
    }
}

// ---------- Ringkasan per status ----------
$ringkas = array_fill_keys($daftar_status, 0);
$qr = q("SELECT p.status, COUNT(*) AS c FROM peminjaman p WHERE 1=1$own GROUP BY p.status");
while ($x = $qr->fetch_assoc()) { if (isset($ringkas[$x['status']])) $ringkas[$x['status']] = (int)$x['c']; }

// ---------- Data + paging ----------
$total  = (int)qx("SELECT COUNT(DISTINCT p.id_peminjaman) AS c $from $where", $tp, $pr)->fetch_assoc()['c'];
$jmlHal = max(1, (int)ceil($total / $perHal));
$hal    = min($hal, $jmlHal);
$offset = ($hal - 1) * $perHal;

$sql = "SELECT p.*, u.nama_lengkap, u.kelas_jabatan, r.nama_ruangan, 
               COALESCE(pg.denda, 0) AS denda_real, pg.tanggal_dikembalikan,
               (SELECT SUM(d.jumlah_pinjam) FROM detail_peminjaman d WHERE d.id_peminjaman = p.id_peminjaman) AS total_jumlah
        $from $where 
        ORDER BY p.id_peminjaman DESC 
        LIMIT $perHal OFFSET $offset";
$data = qx($sql, $tp, $pr);

function url_hal($hal, $status, $jenis, $cari) {
    return 'riwayat.php?' . http_build_query(array_filter(['status' => $status, 'jenis' => $jenis, 'cari' => $cari, 'hal' => $hal > 1 ? $hal : null]));
}
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $is_staff ? 'Semua Riwayat' : 'Riwayat Saya' ?> - Sarpras SMK Negeri 3 Kendal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
 --ink:#0D2B2E; --teal:#0F5F58; --teal-deep:#0A3A36; --sun:#FFC54D; --sun-deep:#B47A00; --sun-soft:#FFE3A1;
 --cream:#E3EDE9; --ivory:#F6FAF8; --line:#CDDFD8; --muted:#5F7A78; --red:#C92A3A;
 --serif:'Cormorant Garamond',Georgia,serif; --sans:'Plus Jakarta Sans',system-ui,sans-serif;
 --grad:linear-gradient(135deg,#0A4A46 0%,#0F7D70 55%,#17A38D 115%);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);color:var(--ink);min-height:100vh;display:flex;overflow:hidden;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%230F5F58' stroke-opacity='.07' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%230F5F58' fill-opacity='.12'/%3E%3C/svg%3E") 0 0/56px 56px,var(--cream)}
.main-content{margin-left:280px;flex:1;height:100vh;overflow-y:auto;padding:30px 34px 40px;scrollbar-width:thin;scrollbar-color:var(--teal) transparent}
.main-content::-webkit-scrollbar{width:8px}
.main-content::-webkit-scrollbar-thumb{background:linear-gradient(var(--teal),#17A38D);border-radius:8px}

.page-head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px}
.page-head h1{font-family:var(--serif);font-size:30px;line-height:1.05}
.page-head p{margin-top:4px;font-size:12.5px;color:var(--muted)}

.flash{display:flex;align-items:center;gap:10px;padding:13px 18px;margin-bottom:18px;border-radius:14px;font-size:13px;font-weight:600}
.flash.ok{background:rgba(15,157,138,.14);color:var(--teal);border:1px solid rgba(15,157,138,.35)}
.flash.err{background:rgba(220,53,69,.12);color:var(--red);border:1px solid rgba(220,53,69,.35)}

/* Card Ringkasan Lebih Panjang & Lebar Proporsional */
.ringkas{display:grid;grid-template-columns:repeat(5, 1fr);gap:14px;margin-bottom:20px}
.ringkas a{display:flex;justify-content:space-between;align-items:center;padding:16px 22px;border-radius:16px;text-decoration:none;color:var(--ink);background:var(--ivory);border:1px solid var(--line);box-shadow:0 8px 20px -10px rgba(15,95,88,.3);transition:.2s}
.ringkas a:hover{transform:translateY(-2px);border-color:var(--teal)}
.ringkas a.on{border-color:var(--teal);box-shadow:0 0 0 2px rgba(15,157,138,.2)}
.ringkas small{display:block;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:2px}
.ringkas b{font-family:var(--serif);font-size:26px;line-height:1;color:var(--teal)}

.toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px}
.inp{padding:10px 14px;border-radius:12px;border:1px solid var(--line);background:#fff;font:500 13px var(--sans);color:var(--ink);outline:none}
.inp:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,157,138,.15)}
.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border:0;border-radius:12px;cursor:pointer;font:700 13px var(--sans);text-decoration:none;color:#fff;background:var(--grad);box-shadow:0 10px 20px -12px rgba(10,74,70,.8);transition:.2s}
.btn:hover{transform:translateY(-2px)}
.btn.ghost{color:var(--teal);background:var(--cream);border:1px solid var(--line);box-shadow:none}

.action-container{display:flex;flex-direction:row;gap:6px;align-items:center}
.btn-action{width:32px;height:32px;border-radius:8px;font-size:12px;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,.1);transition:.15s;border:0;cursor:pointer}
.btn-action:hover{transform:translateY(-1px)}
.btn-detail{background:var(--cream);color:var(--teal);border:1px solid var(--line)}
.btn-batal{background:#dc3545;color:#fff}

.card-box{padding:24px;border-radius:22px;background:var(--ivory);border:1px solid var(--line);box-shadow:0 14px 28px -16px rgba(15,95,88,.2)}
.table-wrap{width:100%;overflow-x:auto}
table{width:100%;border-collapse:separate;border-spacing:0;text-align:left;font-size:13px}
th{padding:13px 14px;font-size:10.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#E6F5F0;background:var(--grad);white-space:nowrap}
th:first-child{border-radius:12px 0 0 12px}th:last-child{border-radius:0 12px 12px 0}
td{padding:14px;border-bottom:1px solid var(--line);vertical-align:middle}
tbody tr:hover td{background:rgba(23,163,141,.07)}
tr:last-child td{border-bottom:0}
.sub{display:block;margin-top:2px;font-size:11.5px;color:var(--muted)}
.badge{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:10.5px;font-weight:700;white-space:nowrap}
.badge::before{content:"";width:5px;height:5px;border-radius:50%;background:currentColor}
.badge.pending{background:rgba(255,197,77,.22);color:#8a6100;border:1px solid rgba(255,197,77,.5)}
.badge.disetujui{background:rgba(15,157,138,.14);color:var(--teal);border:1px solid rgba(15,157,138,.35)}
.badge.selesai{background:rgba(13,110,253,.2);color:#0d6efd;border:1px solid rgba(13,110,253,.35)}
.badge.dikembalikan{background:rgba(40,167,69,.14);color:#198754;border:1px solid rgba(40,167,69,.35)}
.badge.ditolak{background:rgba(220,53,69,.13);color:var(--red);border:1px solid rgba(220,53,69,.35)}
.kosong-box{padding:40px 10px;text-align:center;font-size:13px;color:var(--muted)}
.kosong-box i{display:block;margin-bottom:10px;font-size:32px;color:#A9CFC5}

.paging{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:18px;font-size:12.5px;color:var(--muted)}
.paging .nav{display:flex;gap:6px}
.paging a,.paging span.cur{padding:7px 13px;border-radius:10px;font-weight:700;text-decoration:none;color:var(--teal);background:var(--cream);border:1px solid var(--line)}
.paging span.cur{color:#fff;background:var(--grad);border-color:transparent}
.paging a.off{pointer-events:none;opacity:.4}
.footer{margin-top:26px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1200px){.ringkas{grid-template-columns:repeat(3,1fr)}}
@media(max-width:768px){.main-content{margin-left:72px;padding:16px}.ringkas{grid-template-columns:repeat(2,1fr)}}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">

 <div class="page-head">
  <div>
   <h1><?= $is_staff ? 'Semua Riwayat Peminjaman' : 'Riwayat Peminjaman Saya' ?></h1>
   <p><?= $is_staff ? 'Seluruh pengajuan alat dan booking ruangan/lab dari semua pengguna.' : 'Pantau status pengajuan alat dan booking ruangan/lab milik Anda.' ?></p>
  </div>
  <a href="ajukan.php" class="btn"><i class="fa-solid fa-circle-plus"></i> Ajukan Baru</a>
 </div>

 <?php if ($flash): ?>
  <div class="flash <?= e($flash[0]) ?>"><i class="fa-solid <?= $flash[0] === 'ok' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= e($flash[1]) ?></div>
 <?php endif; ?>

 <div class="ringkas">
  <?php foreach ($daftar_status as $s): ?>
   <a href="<?= e(url_hal(1, $s, $jenis, $cari)) ?>" class="<?= $status === $s ? 'on' : '' ?>">
    <div><small><?= e($s) ?></small><b><?= $ringkas[$s] ?></b></div><?= badge($s) ?>
   </a>
  <?php endforeach; ?>
 </div>

 <form class="toolbar" method="get">
  <input class="inp" type="text" name="cari" placeholder="Cari berdasarkan ID Struk (misal: 16 atau STR-0016)..." value="<?= e($cari) ?>" style="min-width:320px">
  <select class="inp" name="status" onchange="this.form.submit()">
   <option value="">Semua status</option>
   <?php foreach ($daftar_status as $s): ?><option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
  </select>
  <select class="inp" name="jenis" onchange="this.form.submit()">
   <option value="">Alat &amp; Ruangan</option>
   <option value="alat" <?= $jenis === 'alat' ? 'selected' : '' ?>>Alat saja</option>
   <option value="ruangan" <?= $jenis === 'ruangan' ? 'selected' : '' ?>>Ruangan saja</option>
  </select>
  <button class="btn"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
  <?php if ($status || $jenis || $cari): ?><a class="btn ghost" href="riwayat.php"><i class="fa-solid fa-xmark"></i> Reset</a><?php endif; ?>
 </form>

 <section class="card-box">
  <div class="table-wrap">
   <table>
    <thead>
     <tr>
      <th>ID Struk</th>
      <?php if ($is_staff): ?><th>Peminjam</th><?php endif; ?>
      <th>Kelas Peminjam</th>
      <th>Jml</th>
      <th>Tgl Pakai</th>
      <th>Status</th>
      <th>Denda</th>
      <th>Aksi</th>
     </tr>
    </thead>
    <tbody>
     <?php if ($data && $data->num_rows > 0): while ($t = $data->fetch_assoc()):
        $isRuang   = (int)($t['id_ruangan'] ?? 0) > 0;
        $rencana   = $t['tanggal_kembali_rencana'] ?? $t['tanggal_pinjam'];
        $terlambat = ($t['status'] === 'Disetujui' && !$isRuang && $rencana && $rencana < $today);
        $denda     = (int)($t['denda_real'] ?? 0);
     ?>
      <tr>
       <td><b><?= '#STR-' . sprintf('%04d', $t['id_peminjaman']) ?></b></td>
       <?php if ($is_staff): ?><td><b><?= e($t['nama_lengkap']) ?></b></td><?php endif; ?>
       <td><?= e($t['kelas_jabatan'] ?? '-') ?></td>
       <td><?= (int)$t['total_jumlah'] > 0 ? (int)$t['total_jumlah'] . ' unit' : ($isRuang ? 'Ruangan' : '-') ?></td>
       <td>
        <?= date('d/m/Y', strtotime($t['tanggal_pinjam'])) ?>
        <?php if (!empty($t['jam_mulai'])): ?><span class="sub"><?= e(substr($t['jam_mulai'], 0, 5)) ?> - <?= e(substr($t['jam_selesai'] ?? '', 0, 5)) ?> WIB</span><?php endif; ?>
       </td>
       <td>
        <?= badge($t['status']) ?>
        <?php if ($terlambat): ?> <span style="font-size:10px;color:var(--red);font-weight:700;">Terlambat</span><?php endif; ?>
       </td>
       <td><?= $denda > 0 ? '<b style="color:var(--red)">Rp ' . number_format($denda, 0, ',', '.') . '</b>' : '<span class="sub">-</span>' ?></td>
       <td>
        <div class="action-container">
         <a href="detail.php?id=<?= $t['id_peminjaman'] ?>" class="btn-action btn-detail" title="Lihat Detail Pesanan"><i class="fa-solid fa-eye"></i></a>
         <?php if (!$is_staff && $t['status'] === 'Pending'): ?>
          <form method="post" onsubmit="return confirm('Batalkan pengajuan ini?')" style="display:inline;">
           <input type="hidden" name="aksi" value="batal"><input type="hidden" name="id" value="<?= (int)$t['id_peminjaman'] ?>">
           <button class="btn-action btn-batal" title="Batalkan Pengajuan"><i class="fa-solid fa-ban"></i></button>
          </form>
         <?php endif; ?>
        </div>
       </td>
      </tr>
     <?php endwhile; else: ?>
      <tr><td colspan="<?= $is_staff ? 8 : 7 ?>"><div class="kosong-box"><i class="fa-solid fa-folder-open"></i>Tidak ada riwayat yang cocok.</div></td></tr>
     <?php endif; ?>
    </tbody>
   </table>
  </div>

  <?php if ($total > 0): ?>
  <div class="paging">
   <div>Menampilkan <?= $offset + 1 ?>-<?= min($offset + $perHal, $total) ?> dari <?= $total ?> transaksi</div>
   <div class="nav">
    <a class="<?= $hal <= 1 ? 'off' : '' ?>" href="<?= e(url_hal($hal - 1, $status, $jenis, $cari)) ?>"><i class="fa-solid fa-chevron-left"></i></a>
    <span class="cur"><?= $hal ?> / <?= $jmlHal ?></span>
    <a class="<?= $hal >= $jmlHal ? 'off' : '' ?>" href="<?= e(url_hal($hal + 1, $status, $jenis, $cari)) ?>"><i class="fa-solid fa-chevron-right"></i></a>
   </div>
  </div>
  <?php endif; ?>
 </section>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
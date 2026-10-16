<?php
// admin/kategori.php - Manajemen Kategori Alat Sekolah (Hanya untuk Admin)
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); exit; }

$level_login = strtolower($_SESSION['u']['level'] ?? $_SESSION['u']['role'] ?? '');
// Pembatasan hak akses: Hanya Admin yang diizinkan mengakses halaman ini
if ($level_login !== 'admin') {
    echo "<script>alert('Akses ditolak! Halaman Kelola Kategori hanya khusus untuk Administrator.'); window.location='../petugas/index.php';</script>";
    exit;
}

$self = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$pesan = ''; $sukses = false;

// ---------- Proses Hapus Kategori ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $id_hapus = (int)($_POST['id_kategori'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_kat'] = ['Sesi tidak valid, muat ulang halaman lalu coba lagi.', 'error'];
    } else {
        $q_f = q("SELECT nama_kategori FROM kategori WHERE id_kategori = ?", 'i', $id_hapus);
        $data = $q_f ? $q_f->fetch_assoc() : null;

        if (!$data) {
            $_SESSION['flash_kat'] = ['Kategori tidak ditemukan atau sudah dihapus.', 'error'];
        } elseif (q("DELETE FROM kategori WHERE id_kategori = ?", 'i', $id_hapus)) {
            logact('Menghapus kategori ' . $data['nama_kategori']);
            $_SESSION['flash_kat'] = ['Kategori "' . $data['nama_kategori'] . '" berhasil dihapus.', 'success'];
        } else {
            $_SESSION['flash_kat'] = ['Kategori ini tidak bisa dihapus karena masih digunakan oleh data inventaris barang.', 'error'];
        }
    }
    go($self);
}

// ---------- Proses Tambah & Edit Kategori ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['aksi'] ?? '', ['tambah', 'edit'], true)) {
    $aksi          = $_POST['aksi'];
    $nama_kategori = trim($_POST['nama_kategori'] ?? '');
    $id_kategori   = (int)($_POST['id_kategori'] ?? 0);

    if ($aksi === 'tambah') {
        if ($nama_kategori !== '') {
            $ok = q("INSERT INTO kategori (nama_kategori) VALUES (?)", 's', $nama_kategori);
            if ($ok) { $sukses = true; $pesan = "Kategori baru berhasil ditambahkan!"; logact('Menambah kategori ' . $nama_kategori); }
            else { $pesan = "Gagal menyimpan. Nama kategori mungkin sudah ada."; }
        } else {
            $pesan = "Nama kategori tidak boleh kosong.";
        }
    } elseif ($aksi === 'edit' && $id_kategori > 0) {
        if ($nama_kategori !== '') {
            $ok = q("UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?", 'si', $nama_kategori, $id_kategori);
            if ($ok) { $sukses = true; $pesan = "Kategori berhasil diperbarui!"; logact('Mengubah kategori ' . $nama_kategori); }
            else { $pesan = "Gagal memperbarui kategori."; }
        } else {
            $pesan = "Nama kategori wajib diisi.";
        }
    }
}

$flash_kat = $_SESSION['flash_kat'] ?? null;
unset($_SESSION['flash_kat']);

// ---------- Pencarian Data Kategori ----------
$cari = trim($_GET['cari'] ?? '');
$sql = "SELECT * FROM kategori WHERE 1=1";
$params = [];
$types  = '';

if ($cari !== '') {
    $sql .= " AND nama_kategori LIKE ?";
    $params[] = "%$cari%";
    $types  .= 's';
}
$sql .= " ORDER BY id_kategori DESC LIMIT 100";

$q_kategori = !empty($params) ? q($sql, $types, ...$params) : q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Kategori Alat - Sarpras SMK Negeri 3 Kendal</title>
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

.content-grid{display:grid;grid-template-columns:340px 1fr;gap:24px}
.card{background:var(--ivory);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 25px -15px rgba(15,95,88,.15)}
.card h3{font-family:var(--serif);font-size:20px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)}

.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:11.5px;font-weight:700;color:var(--teal);margin-bottom:4px;text-transform:uppercase}
.form-control{width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--line);font-size:13px;background:#fff;outline:none}
.form-control:focus{border-color:var(--teal)}

.btn-submit{width:100%;padding:10px;border-radius:10px;background:var(--grad-hidup);color:#fff;border:0;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 4px 12px rgba(15,125,112,.3)}

.filter-bar{display:flex;gap:8px;margin-bottom:16px;align-items:center}
.filter-bar input{flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--line);font-size:12.5px;background:#fff;outline:none}
.btn-filter{padding:8px 14px;border-radius:8px;background:var(--teal);color:#fff;border:0;font-weight:700;font-size:12px;cursor:pointer}
.btn-shortcut{padding:6px 10px;border-radius:8px;background:var(--cream);color:var(--teal);border:1px solid var(--line);font-weight:700;font-size:11px;text-decoration:none}
.btn-shortcut:hover, .btn-shortcut.active{background:var(--teal);color:#fff}

table{width:100%;border-collapse:collapse;font-size:12.5px}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-weight:700;color:var(--teal);text-transform:uppercase;font-size:10.5px;background:rgba(15,95,88,.05)}

.aksi-wrap{display:flex;gap:6px;align-items:center}
.aksi-wrap form{margin:0}
.btn-action{padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;cursor:pointer;border:0;font-family:inherit}
.btn-edit{background:rgba(255,193,7,.2);color:#b38600}
.btn-hapus{background:rgba(220,53,69,.2);color:#dc3545}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,42,39,0.6);backdrop-filter:blur(4px);z-index:1000;place-items:center;padding:20px}
.modal-overlay.active{display:grid}
.modal-card{background:var(--ivory);border:1px solid var(--line);border-radius:24px;width:100%;max-width:400px;padding:28px;box-shadow:0 20px 40px rgba(0,0,0,.3)}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)}
.modal-header h3{font-family:var(--serif);font-size:20px;font-weight:700}
.modal-close{background:0;border:0;font-size:18px;cursor:pointer;color:var(--muted)}

.alert{padding:12px;border-radius:10px;margin-bottom:16px;font-size:12.5px;font-weight:600}
.alert.success{background:rgba(25,135,84,.15);color:#198754}
.alert.error{background:rgba(220,53,69,.15);color:#dc3545}
.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.content-grid{grid-template-columns:1fr}.main-content{margin-left:72px;padding:16px}}
</style>
<script>
function bukaModalEditKat(btn) {
    var d = JSON.parse(btn.getAttribute('data-k'));
    document.getElementById('edit_id_kategori').value = d.id;
    document.getElementById('edit_nama_kategori').value = d.nama;
    document.getElementById('modalEditKat').classList.add('active');
}
function tutupModalEditKat() {
    document.getElementById('modalEditKat').classList.remove('active');
}
</script>
</head>
<body>
<?php include __DIR__ . '/../petugas/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1>Kelola Kategori Alat</h1>
   <p style="font-size:12.5px;color:var(--muted)">Pengelompokan jenis alat dan inventaris sekolah.</p>
  </div>
 </div>

 <?php if ($flash_kat): ?>
  <div class="alert <?= e($flash_kat[1]) ?>" role="alert"><?= e($flash_kat[0]) ?></div>
 <?php endif; ?>

 <div class="content-grid">
  <!-- Form Tambah Kategori -->
  <div class="card">
   <h3>Tambah Kategori Baru</h3>
   <?php if ($pesan !== '' && ($_POST['aksi'] ?? '') === 'tambah'): ?>
    <div class="alert <?= $sukses ? 'success' : 'error' ?>"><?= e($pesan) ?></div>
   <?php endif; ?>

   <form method="POST">
    <input type="hidden" name="aksi" value="tambah">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <div class="form-group">
     <label>Nama Kategori</label>
     <input type="text" name="nama_kategori" class="form-control" placeholder="Contoh: Elektronika, Perabot..." required>
    </div>
    <button type="submit" class="btn-submit"><i class="fa-solid fa-plus"></i> Simpan Kategori</button>
   </form>
  </div>

  <!-- Tabel Daftar Kategori -->
  <div class="card">
   <h3>Daftar Kategori Terdaftar</h3>
   <?php if ($pesan !== '' && ($_POST['aksi'] ?? '') === 'edit'): ?>
    <div class="alert <?= $sukses ? 'success' : 'error' ?>"><?= e($pesan) ?></div>
   <?php endif; ?>

   <form method="GET" class="filter-bar">
    <input type="text" name="cari" placeholder="Cari nama kategori..." value="<?= e($cari) ?>">
    <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i></button>
    <a href="<?= e($self) ?>" class="btn-shortcut <?= $cari === '' ? 'active' : '' ?>">Semua</a>
   </form>

   <table>
    <thead>
     <tr>
      <th style="width:10%">ID</th>
      <th style="width:60%">Nama Kategori</th>
      <th style="width:30%">Aksi</th>
     </tr>
    </thead>
    <tbody>
     <?php if ($q_kategori && $q_kategori->num_rows > 0): ?>
         <?php while ($row = $q_kategori->fetch_assoc()):
             $data_json = json_encode(['id' => (int)$row['id_kategori'], 'nama' => $row['nama_kategori']]);
         ?>
         <tr>
          <td>#<?= (int)$row['id_kategori'] ?></td>
          <td><b><?= e($row['nama_kategori']) ?></b></td>
          <td>
           <div class="aksi-wrap">
            <button type="button" class="btn-action btn-edit" title="Edit" data-k="<?= e($data_json) ?>" onclick="bukaModalEditKat(this)"><i class="fa-solid fa-pen-to-square"></i></button>
            <form method="POST" onsubmit="return confirm('Hapus kategori ini?')">
             <input type="hidden" name="aksi" value="hapus">
             <input type="hidden" name="id_kategori" value="<?= (int)$row['id_kategori'] ?>">
             <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
             <button type="submit" class="btn-action btn-hapus" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            </form>
           </div>
          </td>
         </tr>
         <?php endwhile; ?>
     <?php else: ?>
         <tr>
          <td colspan="3" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data kategori yang terdaftar.</td>
         </tr>
     <?php endif; ?>
    </tbody>
   </table>
  </div>
 </div>

 <!-- Modal Edit Kategori -->
 <div class="modal-overlay" id="modalEditKat">
  <div class="modal-card">
   <div class="modal-header">
    <h3>Edit Kategori</h3>
    <button type="button" class="modal-close" onclick="tutupModalEditKat()" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
   </div>
   <form method="POST">
    <input type="hidden" name="aksi" value="edit">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="id_kategori" id="edit_id_kategori">

    <div class="form-group">
     <label>Nama Kategori</label>
     <input type="text" name="nama_kategori" id="edit_nama_kategori" class="form-control" required>
    </div>
    <button type="submit" class="btn-submit" style="margin-top:8px"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
   </form>
  </div>
 </div>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
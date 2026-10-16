<?php
// petugas/kelola-inventaris.php - Manajemen Inventaris Alat Sekolah
// Nama file bebas: semua link memakai $self, jadi tidak "Not Found" walau file diganti nama.
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); }

$self = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$pesan = ''; $sukses = false;
$target_dir = __DIR__ . '/../uploads/';
if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

// ---------- Proses Hapus Barang (POST + token) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['aksi'] ?? '') === 'hapus') {
    $id_hapus = (int)($_POST['id_barang'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_inv'] = ['Sesi tidak valid, muat ulang halaman lalu coba lagi.', 'error'];
    } else {
        $q_f  = q("SELECT nama_barang, foto FROM inventaris WHERE id_barang = ?", 'i', $id_hapus);
        $data = $q_f ? $q_f->fetch_assoc() : null;

        if (!$data) {
            $_SESSION['flash_inv'] = ['Barang tidak ditemukan atau sudah dihapus.', 'error'];
        } elseif (q("DELETE FROM inventaris WHERE id_barang = ?", 'i', $id_hapus)) {
            // Foto baru dihapus SETELAH data berhasil terhapus
            if (!empty($data['foto']) && is_file($target_dir . $data['foto'])) { unlink($target_dir . $data['foto']); }
            logact('Menghapus sarpras ' . $data['nama_barang']);
            $_SESSION['flash_inv'] = ['Barang "' . $data['nama_barang'] . '" berhasil dihapus.', 'success'];
        } else {
            $_SESSION['flash_inv'] = ['Barang "' . $data['nama_barang'] . '" tidak bisa dihapus karena sudah tercatat di riwayat peminjaman. Ubah stoknya menjadi 0 atau kondisinya menjadi Rusak sebagai gantinya.', 'error'];
        }
    }
    go($self);
}

// ---------- Proses Tambah & Edit Barang ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['aksi'] ?? '', ['tambah', 'edit'], true)) {
    $aksi          = $_POST['aksi'];
    $nama_barang   = trim($_POST['nama_barang'] ?? '');
    $nama_kategori = trim($_POST['kategori_teks'] ?? 'Elektronika');

    // Konversi kategori teks ke id_kategori sesuai kebutuhan database
    $id_kategori = 1;
    if ($nama_kategori === 'Perabot') { $id_kategori = 2; }
    elseif ($nama_kategori === 'Lainnya') { $id_kategori = 3; }

    $kode_inventaris = trim($_POST['kode_inventaris'] ?? '') ?: 'INV-' . time();
    $stok            = (int)($_POST['stok'] ?? 0);
    $kondisi         = trim($_POST['kondisi'] ?? 'Baik');
    $id_barang       = (int)($_POST['id_barang'] ?? 0);
    $foto_nama       = '';

    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $file_tmp   = $_FILES['foto']['tmp_name'];
        $file_name  = time() . '_' . preg_replace("/[^a-zA-Z0-9._-]/", "", basename($_FILES['foto']['name']));
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'webp'], true) && $_FILES['foto']['size'] <= 2097152) {
            if (move_uploaded_file($file_tmp, $target_file)) { $foto_nama = $file_name; }
        }
    }

    if ($aksi === 'tambah') {
        if ($nama_barang !== '' && $stok >= 0) {
            $ok = q("INSERT INTO inventaris (id_kategori, nama_barang, kode_inventaris, stok, kondisi, foto) VALUES (?, ?, ?, ?, ?, ?)", 'ississ', $id_kategori, $nama_barang, $kode_inventaris, $stok, $kondisi, $foto_nama);
            if ($ok) { $sukses = true; $pesan = "Barang inventaris berhasil ditambahkan!"; logact('Menambah sarpras ' . $nama_barang); }
            else { $pesan = "Gagal menyimpan. Kode inventaris mungkin sudah dipakai."; }
        } else {
            $pesan = "Nama barang dan stok tidak boleh kosong.";
        }
    } elseif ($aksi === 'edit' && $id_barang > 0) {
        if ($nama_barang !== '') {
            if ($foto_nama !== '') {
                $q_old = q("SELECT foto FROM inventaris WHERE id_barang = ?", 'i', $id_barang);
                $old_data = $q_old ? $q_old->fetch_assoc() : null;
                $ok = q("UPDATE inventaris SET id_kategori = ?, nama_barang = ?, kode_inventaris = ?, stok = ?, kondisi = ?, foto = ? WHERE id_barang = ?", 'ississi', $id_kategori, $nama_barang, $kode_inventaris, $stok, $kondisi, $foto_nama, $id_barang);
                if ($ok && $old_data && !empty($old_data['foto']) && is_file($target_dir . $old_data['foto'])) { unlink($target_dir . $old_data['foto']); }
            } else {
                $ok = q("UPDATE inventaris SET id_kategori = ?, nama_barang = ?, kode_inventaris = ?, stok = ?, kondisi = ? WHERE id_barang = ?", 'issisi', $id_kategori, $nama_barang, $kode_inventaris, $stok, $kondisi, $id_barang);
            }
            if ($ok) { $sukses = true; $pesan = "Data inventaris berhasil diperbarui!"; logact('Mengubah sarpras ' . $nama_barang); }
            else { $pesan = "Gagal memperbarui. Kode inventaris mungkin sudah dipakai."; }
        } else {
            $pesan = "Nama barang wajib diisi.";
        }
    }
}

// Pesan sekali tampil (hasil hapus)
$flash_inv = $_SESSION['flash_inv'] ?? null;
unset($_SESSION['flash_inv']);

// ---------- Pencarian & Filter Kategori ----------
$cari = trim($_GET['cari'] ?? '');
$kat  = (int)($_GET['id_kategori'] ?? 0);

$sql = "SELECT * FROM inventaris WHERE 1=1";
$params = [];
$types  = '';

if ($cari !== '') {
    $sql .= " AND (nama_barang LIKE ? OR kode_inventaris LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $types  .= 'ss';
}
if ($kat > 0) {
    $sql .= " AND id_kategori = ?";
    $params[] = $kat;
    $types  .= 'i';
}
$sql .= " ORDER BY id_barang DESC LIMIT 200";

$q_inventaris = !empty($params) ? q($sql, $types, ...$params) : q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Inventaris - Sarpras SMK Negeri 3 Kendal</title>
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

.filter-bar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center}
.filter-bar input{flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--line);font-size:12.5px;background:#fff;outline:none}
.btn-filter{padding:8px 14px;border-radius:8px;background:var(--teal);color:#fff;border:0;font-weight:700;font-size:12px;cursor:pointer}
.btn-shortcut{padding:6px 10px;border-radius:8px;background:var(--cream);color:var(--teal);border:1px solid var(--line);font-weight:700;font-size:11px;text-decoration:none}
.btn-shortcut:hover, .btn-shortcut.active{background:var(--teal);color:#fff}

table{width:100%;border-collapse:collapse;font-size:12.5px}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-weight:700;color:var(--teal);text-transform:uppercase;font-size:10.5px;background:rgba(15,95,88,.05)}

.barang-img{width:45px;height:45px;border-radius:8px;object-fit:cover;border:1px solid var(--line);background:#fff}
.badge{display:inline-block;padding:4px 8px;border-radius:999px;font-size:9.5px;font-weight:700;text-transform:uppercase}
.badge.baik{background:rgba(25,135,84,.2);color:#198754}
.badge.rusak{background:rgba(220,53,69,.2);color:#dc3545}

.aksi-wrap{display:flex;gap:6px;align-items:center}
.aksi-wrap form{margin:0}
.btn-action{padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;cursor:pointer;border:0;font-family:inherit}
.btn-edit{background:rgba(255,193,7,.2);color:#b38600}
.btn-hapus{background:rgba(220,53,69,.2);color:#dc3545}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,42,39,0.6);backdrop-filter:blur(4px);z-index:1000;place-items:center;padding:20px}
.modal-overlay.active{display:grid}
.modal-card{background:var(--ivory);border:1px solid var(--line);border-radius:24px;width:100%;max-width:440px;max-height:calc(100vh - 40px);overflow-y:auto;padding:28px;box-shadow:0 20px 40px rgba(0,0,0,.3)}
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
var KAT = {1: 'Elektronika', 2: 'Perabot', 3: 'Lainnya'};
function bukaModalEdit(btn) {
    var d = JSON.parse(btn.getAttribute('data-b'));
    document.getElementById('edit_id_barang').value = d.id;
    document.getElementById('edit_nama_barang').value = d.nama;
    document.getElementById('edit_kode_inventaris').value = d.kode;
    document.getElementById('edit_stok').value = d.stok;
    document.getElementById('edit_kondisi').value = d.kondisi;
    document.getElementById('edit_id_kategori').value = KAT[d.kat] || 'Elektronika';
    document.getElementById('modalEdit').classList.add('active');
}
function tutupModalEdit() {
    document.getElementById('modalEdit').classList.remove('active');
}
</script>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1>Kelola Inventaris Alat & Barang</h1>
   <p style="font-size:12.5px;color:var(--muted)">Tambah, perbarui, dan pantau ketersediaan stok inventaris sekolah.</p>
  </div>
 </div>

 <div class="content-grid">
  <!-- Form Tambah -->
  <div class="card">
   <h3>Tambah Barang Baru</h3>
   <?php if ($pesan !== '' && ($_POST['aksi'] ?? '') === 'tambah'): ?>
    <div class="alert <?= $sukses ? 'success' : 'error' ?>"><?= e($pesan) ?></div>
   <?php endif; ?>

   <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="aksi" value="tambah">
    <div class="form-group">
     <label>Nama Barang / Alat</label>
     <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: Laptop Core i5" required>
    </div>
    <div class="form-group">
     <label>Kode Inventaris</label>
     <input type="text" name="kode_inventaris" class="form-control" placeholder="Contoh: INV-001" required>
    </div>
    <div class="form-group">
     <label>Kategori Barang</label>
     <select name="kategori_teks" class="form-control">
      <option value="Elektronika">1 - Elektronika</option>
      <option value="Perabot">2 - Perabot</option>
      <option value="Lainnya">3 - Lainnya</option>
     </select>
    </div>
    <div class="form-group">
     <label>Stok Unit</label>
     <input type="number" name="stok" class="form-control" value="1" min="0" required>
    </div>
    <div class="form-group">
     <label>Kondisi Barang</label>
     <select name="kondisi" class="form-control">
      <option value="Baik">Baik</option>
      <option value="Perlu Perbaikan">Perlu Perbaikan</option>
      <option value="Rusak">Rusak</option>
     </select>
    </div>
    <div class="form-group">
     <label>Foto Barang (Opsional)</label>
     <input type="file" name="foto" class="form-control" accept="image/*">
    </div>
    <button type="submit" class="btn-submit"><i class="fa-solid fa-plus"></i> Simpan Barang</button>
   </form>
  </div>

  <!-- Tabel & Filter -->
  <div class="card">
   <h3>Daftar Inventaris Tersedia</h3>
   <?php if ($pesan !== '' && ($_POST['aksi'] ?? '') === 'edit'): ?>
    <div class="alert <?= $sukses ? 'success' : 'error' ?>"><?= e($pesan) ?></div>
   <?php endif; ?>
   <?php if ($flash_inv): ?>
    <div class="alert <?= e($flash_inv[1]) ?>" role="alert"><?= e($flash_inv[0]) ?></div>
   <?php endif; ?>

   <form method="GET" class="filter-bar">
    <input type="text" name="cari" placeholder="Cari nama atau kode barang..." value="<?= e($cari) ?>">
    <?php if ($kat > 0): ?><input type="hidden" name="id_kategori" value="<?= $kat ?>"><?php endif; ?>
    <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i></button>
    <a href="<?= e($self) ?>" class="btn-shortcut <?= $kat === 0 ? 'active' : '' ?>">Semua</a>
    <a href="<?= e($self) ?>?id_kategori=1" class="btn-shortcut <?= $kat === 1 ? 'active' : '' ?>">1-Elektronika</a>
    <a href="<?= e($self) ?>?id_kategori=2" class="btn-shortcut <?= $kat === 2 ? 'active' : '' ?>">2-Perabot</a>
    <a href="<?= e($self) ?>?id_kategori=3" class="btn-shortcut <?= $kat === 3 ? 'active' : '' ?>">3-Lainnya</a>
   </form>

   <table>
    <thead>
     <tr>
      <th style="width:10%">Foto</th>
      <th style="width:25%">Nama Barang</th>
      <th style="width:15%">Kategori</th>
      <th style="width:12%">Stok</th>
      <th style="width:18%">Kondisi</th>
      <th style="width:20%">Aksi</th>
     </tr>
    </thead>
    <tbody>
     <?php if ($q_inventaris && $q_inventaris->num_rows > 0): ?>
         <?php while ($row = $q_inventaris->fetch_assoc()):
             $kondisi_cls = strtolower($row['kondisi']) === 'baik' ? 'baik' : 'rusak';
             $path_foto = (!empty($row['foto']) && is_file(__DIR__ . '/../uploads/' . $row['foto'])) ? '../uploads/' . $row['foto'] : 'https://placehold.co/100x100?text=No+Img';

             $lbl_kat = '1-Elektronika';
             if ((int)$row['id_kategori'] === 2) { $lbl_kat = '2-Perabot'; }
             elseif ((int)$row['id_kategori'] === 3) { $lbl_kat = '3-Lainnya'; }

             $data_edit = json_encode([
                 'id' => (int)$row['id_barang'], 'nama' => $row['nama_barang'], 'kode' => $row['kode_inventaris'],
                 'stok' => (int)$row['stok'], 'kondisi' => $row['kondisi'], 'kat' => (int)$row['id_kategori'],
             ]);
         ?>
         <tr>
          <td><img src="<?= e($path_foto) ?>" alt="Foto" class="barang-img"></td>
          <td><b><?= e($row['nama_barang']) ?></b><br><span style="font-size:10.5px;color:var(--muted)"><?= e($row['kode_inventaris']) ?></span></td>
          <td><span style="font-size:11px;color:var(--teal);font-weight:700"><?= $lbl_kat ?></span></td>
          <td><?= (int)$row['stok'] ?> Unit</td>
          <td><span class="badge <?= $kondisi_cls ?>"><?= e($row['kondisi']) ?></span></td>
          <td>
           <div class="aksi-wrap">
            <button type="button" class="btn-action btn-edit" title="Edit" data-b="<?= e($data_edit) ?>" onclick="bukaModalEdit(this)"><i class="fa-solid fa-pen-to-square"></i></button>
            <form method="POST" onsubmit="return confirm('Hapus barang ini?')">
             <input type="hidden" name="aksi" value="hapus">
             <input type="hidden" name="id_barang" value="<?= (int)$row['id_barang'] ?>">
             <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
             <button type="submit" class="btn-action btn-hapus" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            </form>
           </div>
          </td>
         </tr>
         <?php endwhile; ?>
     <?php else: ?>
         <tr>
          <td colspan="6" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data inventaris barang yang terdaftar.</td>
         </tr>
     <?php endif; ?>
    </tbody>
   </table>
  </div>
 </div>

 <!-- Modal Edit -->
 <div class="modal-overlay" id="modalEdit">
  <div class="modal-card">
   <div class="modal-header">
    <h3>Edit Data Barang</h3>
    <button type="button" class="modal-close" onclick="tutupModalEdit()" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
   </div>
   <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="aksi" value="edit">
    <input type="hidden" name="id_barang" id="edit_id_barang">

    <div class="form-group">
     <label>Nama Barang / Alat</label>
     <input type="text" name="nama_barang" id="edit_nama_barang" class="form-control" required>
    </div>
    <div class="form-group">
     <label>Kode Inventaris</label>
     <input type="text" name="kode_inventaris" id="edit_kode_inventaris" class="form-control" required>
    </div>
    <div class="form-group">
     <label>Kategori Barang</label>
     <select name="kategori_teks" id="edit_id_kategori" class="form-control">
      <option value="Elektronika">1 - Elektronika</option>
      <option value="Perabot">2 - Perabot</option>
      <option value="Lainnya">3 - Lainnya</option>
     </select>
    </div>
    <div class="form-group">
     <label>Stok Unit</label>
     <input type="number" name="stok" id="edit_stok" class="form-control" min="0" required>
    </div>
    <div class="form-group">
     <label>Kondisi Barang</label>
     <select name="kondisi" id="edit_kondisi" class="form-control">
      <option value="Baik">Baik</option>
      <option value="Perlu Perbaikan">Perlu Perbaikan</option>
      <option value="Rusak">Rusak</option>
     </select>
    </div>
    <div class="form-group">
     <label>Ganti Foto (Opsional)</label>
     <input type="file" name="foto" class="form-control" accept="image/*">
    </div>
    <button type="submit" class="btn-submit" style="margin-top:8px"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
   </form>
  </div>
 </div>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
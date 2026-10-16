<?php
// petugas/pengajuan.php - Form Pengajuan dengan Sistem Keranjang Multi-Alat
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); exit; }

// Inisialisasi Keranjang Sesi untuk Alat
if (!isset($_SESSION['cart_alat'])) { $_SESSION['cart_alat'] = []; }

// Aksi Tambah / Hapus Barang dari Keranjang via URL (?aksi=tambah&id=X atau ?aksi=hapus&id=X)
if (isset($_GET['aksi'], $_GET['id'])) {
    $id_b = (int)$_GET['id'];
    if ($_GET['aksi'] === 'tambah' && $id_b > 0) {
        // Cek stok barang di database
        $qb = q("SELECT * FROM inventaris WHERE id_barang = ? AND stok > 0", 'i', $id_b)->fetch_assoc();
        if ($qb) {
            if (isset($_SESSION['cart_alat'][$id_b])) {
                // Jika sudah ada di keranjang, tambah jumlahnya (asal tidak melebihi stok)
                if ($_SESSION['cart_alat'][$id_b]['jumlah'] < (int)$qb['stok']) {
                    $_SESSION['cart_alat'][$id_b]['jumlah']++;
                }
            } else {
                // Masukkan baru ke keranjang
                $_SESSION['cart_alat'][$id_b] = [
                    'id_barang' => $qb['id_barang'],
                    'nama_barang' => $qb['nama_barang'],
                    'stok' => (int)$qb['stok'],
                    'jumlah' => 1
                ];
            }
        }
        go('ajukan.php');
        exit;
    } elseif ($_GET['aksi'] === 'hapus' && $id_b > 0) {
        unset($_SESSION['cart_alat'][$id_b]);
        go('ajukan.php');
        exit;
    }
}

// Tangkap Parameter Ruangan jika sedang booking ruangan
$id_ruangan = (int)($_GET['ruangan'] ?? 0);
$info_ruangan = null;
if ($id_ruangan > 0) {
    $q_r = q("SELECT * FROM ruangan WHERE id_ruangan = ?", 'i', $id_ruangan);
    if ($q_r && $q_r->num_rows > 0) { $info_ruangan = $q_r->fetch_assoc(); }
}

// Validasi Tanggal & Jam
function tgl_valid($d) { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }
function jam_valid($j) { return (bool)preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $j); }

$pesan = ''; $sukses = false;

// Proses Simpan Checkout / Kirim Permohonan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_user        = (int)($_SESSION['u']['id_user'] ?? $_SESSION['u']['id'] ?? 0);
    $post_ruangan   = (int)($_POST['id_ruangan'] ?? 0);
    $tanggal_pakai  = trim($_POST['tanggal_pakai'] ?? '');
    $jam_mulai      = trim($_POST['jam_mulai'] ?? '');
    $jam_selesai    = trim($_POST['jam_selesai'] ?? '');
    $kelas_pengguna = trim($_POST['kelas_pengguna'] ?? '');
    $keperluan      = trim($_POST['keperluan'] ?? '');

    // Update jumlah manual dari input post jika ada perubahan
    if (!empty($_POST['jml_ubah'])) {
        foreach ($_POST['jml_ubah'] as $id_b => $val) {
            $id_b = (int)$id_b;
            $val = (int)$val;
            if (isset($_SESSION['cart_alat'][$id_b]) && $val > 0 && $val <= $_SESSION['cart_alat'][$id_b]['stok']) {
                $_SESSION['cart_alat'][$id_b]['jumlah'] = $val;
            }
        }
    }

    if ($id_user <= 0) {
        $pesan = "Sesi login tidak valid. Silakan login ulang.";
    } elseif ($tanggal_pakai === '' || $jam_mulai === '' || $jam_selesai === '' || $kelas_pengguna === '' || $keperluan === '') {
        $pesan = "Mohon lengkapi seluruh kolom formulir jadwal dan keperluan.";
    } elseif (!tgl_valid($tanggal_pakai) || !jam_valid($jam_mulai) || !jam_valid($jam_selesai)) {
        $pesan = "Format tanggal atau jam tidak valid.";
    } elseif ($tanggal_pakai < date('Y-m-d')) {
        $pesan = "Tanggal pelaksanaan tidak boleh sebelum hari ini.";
    } elseif ($jam_selesai <= $jam_mulai) {
        $pesan = "Jam selesai harus lebih besar dari jam mulai.";
    } elseif (empty($_SESSION['cart_alat']) && $post_ruangan <= 0) {
        $pesan = "Keranjang alat masih kosong. Silakan pilih alat dari katalog terlebih dahulu.";
    } else {
        $waktu_sesi        = "$jam_mulai s.d $jam_selesai WIB";
        $keperluan_lengkap = "[Kelas: $kelas_pengguna | Waktu: $waktu_sesi] " . $keperluan;
        $jm = $jam_mulai . ':00';
        $js = $jam_selesai . ':00';

        try {
            if (!empty($_SESSION['cart_alat'])) {
                // ---------- CHECKOUT PEMINJAMAN ALAT ----------
                q("INSERT INTO peminjaman (id_user, tanggal_pinjam, tanggal_kembali_rencana, jam_mulai, jam_selesai, keperluan, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')",
                  'isssss', $id_user, $tanggal_pakai, $tanggal_pakai, $jm, $js, $keperluan_lengkap);
                
                $id_baru = (int)q("SELECT LAST_INSERT_ID() AS id")->fetch_assoc()['id'];
                $total_item = 0;

                foreach ($_SESSION['cart_alat'] as $item) {
                    q("INSERT INTO detail_peminjaman (id_peminjaman, id_barang, jumlah_pinjam) VALUES (?, ?, ?)", 
                      'iii', $id_baru, $item['id_barang'], $item['jumlah']);
                    $total_item++;
                }

                q("INSERT INTO log_aktivitas (id_user, aksi) VALUES (?, ?)", 'is', $id_user, "Mengajukan peminjaman $total_item jenis alat (ID Struk: #PMJ-" . sprintf('%04d', $id_baru) . ")");
                
                // Kosongkan keranjang setelah berhasil diajukan
                $_SESSION['cart_alat'] = [];
                $sukses = true;
                $pesan  = "Pengajuan peminjaman alat berhasil dikirim! Menunggu persetujuan petugas.";

            } else {
                // ---------- BOOKING RUANGAN ----------
                $rg = q("SELECT nama_ruangan FROM ruangan WHERE id_ruangan = ?", 'i', $post_ruangan)->fetch_assoc();
                if (!$rg) {
                    $pesan = "Ruangan tidak ditemukan.";
                } else {
                    $bentrok = (int)q("SELECT COUNT(*) AS c FROM peminjaman
                                        WHERE id_ruangan = ? AND status = 'Disetujui' AND tanggal_pinjam = ?
                                          AND jam_mulai < ? AND jam_selesai > ?",
                                       'isss', $post_ruangan, $tanggal_pakai, $js, $jm)->fetch_assoc()['c'];
                    if ($bentrok > 0) {
                        $pesan = $rg['nama_ruangan'] . " sudah terpakai pada jadwal tersebut.";
                    } else {
                        q("INSERT INTO peminjaman (id_user, id_ruangan, tanggal_pinjam, tanggal_kembali_rencana, jam_mulai, jam_selesai, keperluan, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')",
                          'iisssss', $id_user, $post_ruangan, $tanggal_pakai, $tanggal_pakai, $jm, $js, $keperluan_lengkap);
                        
                        q("INSERT INTO log_aktivitas (id_user, aksi) VALUES (?, ?)", 'is', $id_user, "Mengajukan booking ruangan ID: $post_ruangan");
                        $sukses = true;
                        $pesan  = "Booking ruangan berhasil diajukan!";
                    }
                }
            }
        } catch (Throwable $ex) {
            $pesan = "Terjadi kesalahan saat memproses pengajuan.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Form Pengajuan Sarpras - SMK Negeri 3 Kendal</title>
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

.form-card{max-width:740px;margin:0 auto;background:var(--ivory);border:1px solid var(--line);border-radius:24px;padding:36px;box-shadow:0 15px 35px -15px rgba(15,95,88,.25)}
.form-header{text-align:center;margin-bottom:24px}
.form-header h1{font-family:var(--serif);font-size:26px;font-weight:700;margin-bottom:4px}
.form-header p{font-size:13px;color:var(--muted)}

.alert{padding:14px 18px;border-radius:12px;margin-bottom:20px;font-size:13px;font-weight:600}
.alert.success{background:rgba(40,167,69,.15);color:#198754;border:1px solid rgba(40,167,69,.3)}
.alert.error{background:rgba(220,53,69,.15);color:#dc3545;border:1px solid rgba(220,53,69,.3)}

/* Tabel Daftar Barang di Keranjang */
.cart-table{width:100%;border-collapse:collapse;margin-bottom:16px;font-size:12.5px}
.cart-table th, .cart-table td{padding:10px;border-bottom:1px solid var(--line);text-align:left}
.cart-table th{background:rgba(15,95,88,.05);color:var(--teal);font-weight:700;text-transform:uppercase;font-size:10px}
.btn-tambah-alat{display:inline-flex;align-items:center;gap:6px;padding:10px 16px;background:var(--cream);color:var(--teal);border:1px solid var(--teal);border-radius:12px;font-weight:700;font-size:12px;text-decoration:none;transition:.2s;margin-bottom:20px}
.btn-tambah-alat:hover{background:var(--teal);color:#fff}

.form-group{margin-bottom:18px}
.form-group label{display:block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--teal);margin-bottom:6px}
.form-control{width:100%;padding:12px 16px;border-radius:12px;border:1px solid var(--line);font-size:13.5px;background:#fff;outline:none;font-family:var(--sans)}
.form-control:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,157,138,.15)}
textarea.form-control{resize:vertical;min-height:90px}
.row2{display:grid;grid-template-columns:1fr 1fr;gap:16px}

.btn-submit{display:block;width:100%;padding:14px;border-radius:14px;background:var(--grad-hidup);color:#fff;border:0;font-weight:700;font-size:14px;cursor:pointer;box-shadow:0 10px 20px rgba(15,125,112,.3);transition:.2s}
.btn-submit:hover{transform:translateY(-2px)}
.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:768px){.main-content{margin-left:72px;padding:16px}.form-card{padding:20px}.row2{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include __DIR__ . '/header.php'; ?>
<main class="main-content">
 <div class="form-card">
  <div class="form-header">
   <h1>Form Pengajuan &amp; Pemesanan Sarpras</h1>
   <p>Kelola daftar alat yang ingin dipinjam atau tambah alat lain dari katalog.</p>
  </div>

  <?php if ($pesan !== ''): ?>
   <div class="alert <?= $sukses ? 'success' : 'error' ?>"><?= e($pesan) ?></div>
  <?php endif; ?>

  <form method="POST">
   <?php if ($info_ruangan): ?>
    <input type="hidden" name="id_ruangan" value="<?= (int)$info_ruangan['id_ruangan'] ?>">
    <div style="background:var(--cream);padding:16px;border-radius:14px;margin-bottom:20px;font-size:14px;">
     <b>Booking Ruangan:</b> <?= e($info_ruangan['nama_ruangan']) ?>
    </div>
   <?php else: ?>
    <div class="form-group">
     <label><i class="fa-solid fa-basket-shopping"></i> Daftar Alat yang Dipilih (Keranjang)</label>
     <?php if (!empty($_SESSION['cart_alat'])): ?>
      <table class="cart-table">
       <thead>
        <tr>
         <th>Nama Alat</th>
         <th style="width:90px;text-align:center;">Jumlah</th>
         <th style="width:60px;text-align:center;">Aksi</th>
        </tr>
       </thead>
       <tbody>
        <?php foreach ($_SESSION['cart_alat'] as $item): ?>
         <tr>
          <td><b><?= e($item['nama_barang']) ?></b><br><small style="color:var(--muted)">Stok tersedia: <?= $item['stok'] ?></small></td>
          <td style="text-align:center;">
           <input type="number" name="jml_ubah[<?= $item['id_barang'] ?>]" value="<?= $item['jumlah'] ?>" min="1" max="<?= $item['stok'] ?>" style="width:60px;padding:6px;text-align:center;border:1px solid var(--line);border-radius:8px;font-size:12px;">
          </td>
          <td style="text-align:center;">
           <a href="ajukan.php?aksi=hapus&id=<?= $item['id_barang'] ?>" style="color:var(--red);text-decoration:none;" onclick="return confirm('Hapus alat ini dari daftar?')"><i class="fa-solid fa-trash"></i></a>
          </td>
         </tr>
        <?php endforeach; ?>
       </tbody>
      </table>
     <?php else: ?>
      <div style="text-align:center;padding:20px;background:var(--cream);border-radius:14px;margin-bottom:14px;font-size:13px;color:var(--muted)">
       Belum ada alat di dalam keranjang peminjaman.
      </div>
     <?php endif; ?>
     
     <a href="katalog-alat.php" class="btn-tambah-alat"><i class="fa-solid fa-circle-plus"></i> + Tambah / Pilih Alat Lain dari Katalog</a>
    </div>
   <?php endif; ?>

   <div class="row2">
    <div class="form-group">
     <label>Tanggal Pelaksanaan</label>
     <input type="date" name="tanggal_pakai" class="form-control" value="<?= e($_POST['tanggal_pakai'] ?? date('Y-m-d')) ?>" min="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="form-group">
     <label>Kelas Pengguna</label>
     <input type="text" name="kelas_pengguna" class="form-control" placeholder="Contoh: XII RPL 1" value="<?= e($sukses ? '' : ($_POST['kelas_pengguna'] ?? '')) ?>" required>
    </div>
   </div>

   <div class="row2">
    <div class="form-group">
     <label>Jam Mulai Sesi</label>
     <input type="time" name="jam_mulai" class="form-control" value="<?= e($_POST['jam_mulai'] ?? '07:30') ?>" required>
    </div>
    <div class="form-group">
     <label>Jam Selesai Sesi</label>
     <input type="time" name="jam_selesai" class="form-control" value="<?= e($_POST['jam_selesai'] ?? '12:00') ?>" required>
    </div>
   </div>

   <div class="form-group">
    <label>Keperluan / Kegiatan</label>
    <textarea name="keperluan" class="form-control" placeholder="Contoh: Praktik Mapel Pemrograman Web / Ujian Praktik" required><?= e($sukses ? '' : ($_POST['keperluan'] ?? '')) ?></textarea>
   </div>

   <button type="submit" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Kirim Permohonan Peminjaman</button>
  </form>
 </div>
 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
<?php
// petugas/cetak-struk.php - Cetak Bukti / Nota Peminjaman Satuan (1 Transaksi)
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo "<script>alert('ID Peminjaman tidak valid!'); window.close();</script>";
    exit;
}

// 1. Ambil data utama peminjaman
$q_main = q("SELECT * FROM peminjaman WHERE id_peminjaman = ?", 'i', $id);
if (!$q_main || $q_main->num_rows === 0) {
    echo "<script>alert('Data peminjaman tidak ditemukan!'); window.close();</script>";
    exit;
}
$data = $q_main->fetch_assoc();

// 2. Ambil data user peminjam
$id_user = (int)($data['id_user'] ?? 0);
$user_data = ['nama_lengkap' => 'Tidak diketahui', 'nis_nip' => '-', 'kelas_jabatan' => '-'];
if ($id_user > 0) {
    $q_usr = q("SELECT * FROM users WHERE id_user = ?", 'i', $id_user);
    if ($q_usr && $q_usr->num_rows > 0) {
        $user_data = $q_usr->fetch_assoc();
    }
}

// 3. Ambil data petugas yang memproses (jika ada)
$id_petugas = (int)($data['id_petugas'] ?? 0);
$nama_petugas = 'Sistem / Belum Ditentukan';
if ($id_petugas > 0) {
    $q_ptg = q("SELECT nama_lengkap, nama FROM users WHERE id_user = ?", 'i', $id_petugas);
    if ($q_ptg && $q_ptg->num_rows > 0) {
        $ptg = $q_ptg->fetch_assoc();
        $nama_petugas = $ptg['nama_lengkap'] ?? $ptg['nama'] ?? 'Petugas';
    }
}

// 4. Ambil data ruangan (jika meminjam ruangan)
$id_ruangan = (int)($data['id_ruangan'] ?? 0);
$ruangan_data = null;
if ($id_ruangan > 0) {
    $q_rng = q("SELECT * FROM ruangan WHERE id_ruangan = ?", 'i', $id_ruangan);
    if ($q_rng && $q_rng->num_rows > 0) {
        $ruangan_data = $q_rng->fetch_assoc();
    }
}

// 5. Ambil daftar barang detail peminjaman
$q_detail = q("SELECT dp.*, i.nama_barang, i.kode_inventaris AS kode_barang 
               FROM detail_peminjaman dp 
               JOIN inventaris i ON i.id_barang = dp.id_barang 
               WHERE dp.id_peminjaman = ?", 'i', $id);

// 6. Ambil data pengembalian & denda (jika sudah selesai)
$q_pg = q("SELECT * FROM pengembalian WHERE id_peminjaman = ?", 'i', $id);
$data_pg = ($q_pg && $q_pg->num_rows > 0) ? $q_pg->fetch_assoc() : null;

$is_ruangan = ($id_ruangan > 0);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bukti Peminjaman #STR-<?= sprintf('%04d', $id) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #111; margin: 0; padding: 20px; background: #f4f8f7; font-size: 13px; line-height: 1.5; }
.struk-container { max-width: 700px; margin: 0 auto; border: 1px solid #ccc; padding: 30px; border-radius: 12px; background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
.header-instansi { text-align: center; border-bottom: 2px solid #0F5F58; padding-bottom: 15px; margin-bottom: 20px; }
.header-instansi h2 { margin: 0; font-size: 20px; color: #0F5F58; text-transform: uppercase; letter-spacing: 1px; }
.header-instansi p { margin: 4px 0 0; font-size: 12px; color: #555; }

.info-section { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12.5px; }
.info-box { width: 48%; }
.info-box b { color: #0F5F58; display: inline-block; width: 110px; }

table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12.5px; }
th, td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; }
th { background-color: #f4f8f7; color: #0F5F58; text-transform: uppercase; font-size: 11px; }

.ttd-section { display: flex; justify-content: space-between; margin-top: 40px; text-align: center; font-size: 12.5px; }
.ttd-box { width: 200px; }
.ttd-space { height: 60px; }

/* Area Tombol di Bawah */
.action-bottom-container { max-width: 700px; margin: 20px auto 0; display: flex; gap: 12px; justify-content: center; }
.btn-action { padding: 12px 24px; font-size: 14px; font-weight: bold; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.15); border: none; }
.btn-print { background: #0F5F58; color: #fff; }
.btn-print:hover { background: #0A423D; }
.btn-back { background: #e3ede9; color: #0F5F58; border: 1px solid #cddfd8; }
.btn-back:hover { background: #d0e3de; }

@media print {
    body { padding: 0; background: #fff; }
    .struk-container { border: none; padding: 0; box-shadow: none; }
    .action-bottom-container { display: none; }
}

@media(max-width: 600px) {
    .info-section { flex-direction: column; gap: 10px; }
    .info-box { width: 100%; }
    .action-bottom-container { flex-direction: column; }
    .btn-action { width: 100%; justify-content: center; }
}
</style>
</head>
<body>

<div class="struk-container">
    <div class="header-instansi">
        <h2>SMK Negeri 3 Kendal</h2>
        <p>Sistem Manajemen Sarana dan Prasarana Sekolah (SARPRAS)</p>
        <p style="font-size: 11px; color: #777;">Bukti Transaksi Peminjaman Alat & Fasilitas</p>
    </div>

    <div class="info-section">
        <div class="info-box">
            <p><b>ID Transaksi</b> : #STR-<?= sprintf('%04d', $id) ?></p>
            <p><b>Status</b> : <?= strtoupper(e($data['status'])) ?></p>
            <p><b>Tanggal Pinjam</b> : <?= date('d/m/Y', strtotime($data['tanggal_pinjam'])) ?></p>
            <p><b>Jadwal Sesi</b> : <?= e(substr($data['jam_mulai'] ?? '', 0, 5)) ?> - <?= e(substr($data['jam_selesai'] ?? '', 0, 5)) ?> WIB</p>
        </div>
        <div class="info-box">
            <p><b>Nama Peminjam</b> : <?= e($user_data['nama_lengkap']) ?></p>
            <p><b>NIS / NIP</b> : <?= e($user_data['nis_nip'] ?? '-') ?></p>
            <p><b>Kelas / Jabatan</b> : <?= e($user_data['kelas_jabatan']) ?></p>
            <p><b>Petugas Verifikasi</b> : <?= e($nama_petugas) ?></p>
        </div>
    </div>

    <div style="margin-bottom: 8px; font-weight: bold; color: #0F5F58; font-size: 12px; text-transform: uppercase;">Rincian Barang / Fasilitas Dipinjam:</div>
    
    <?php if ($is_ruangan && $ruangan_data): ?>
        <div style="padding: 12px; background: #f9fbfb; border: 1px solid #ddd; border-radius: 6px;">
            <b>Peminjaman Ruangan:</b> <?= e($ruangan_data['nama_ruangan']) ?> (Kapasitas: <?= e($ruangan_data['kapasitas'] ?? '-') ?> Siswa)
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 15%;">No</th>
                    <th style="width: 30%;">Kode Alat</th>
                    <th style="width: 40%;">Nama Alat / Inventaris</th>
                    <th style="width: 15%; text-align: center;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $no = 1;
                if ($q_detail && $q_detail->num_rows > 0): 
                    while($dt = $q_detail->fetch_assoc()): 
                ?>
                <tr>
                    <td><?= $no++ ?></td>
                    <td><code><?= e($dt['kode_barang'] ?? 'ALAT-'.$dt['id_barang']) ?></code></td>
                    <td><b><?= e($dt['nama_barang']) ?></b></td>
                    <td style="text-align: center;"><?= (int)$dt['jumlah_pinjam'] ?> unit</td>
                </tr>
                <?php 
                    endwhile; 
                else: 
                ?>
                <tr>
                    <td colspan="4" style="text-align: center; color: #777;">Tidak ada rincian alat tercatat.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div style="margin-top: 15px; font-size: 12.5px;">
        <p><b>Keperluan / Keterangan Kegiatan:</b></p>
        <div style="background: #f9fbfb; padding: 10px; border: 1px solid #ddd; border-radius: 6px; min-height: 40px;">
            <?= nl2br(e($data['keperluan'] ?? '-')) ?>
        </div>
    </div>

    <?php if (strtolower($data['status']) === 'selesai' && $data_pg): ?>
    <div style="margin-top: 15px; padding: 10px; background: #e8f5e9; border: 1px solid #c8e6c9; border-radius: 6px; font-size: 12.5px;">
        <p style="margin: 0; color: #2e7d32;"><b>Informasi Pengembalian:</b> Dikembalikan pada tanggal <?= date('d/m/Y', strtotime($data_pg['tanggal_dikembalikan'])) ?>. 
        Denda: <b><?= (int)$data_pg['denda'] > 0 ? 'Rp ' . number_format($data_pg['denda'], 0, ',', '.') : 'Rp 0 (Tepat Waktu)' ?></b></p>
    </div>
    <?php endif; ?>

    <div class="ttd-section">
        <div class="ttd-box">
            <p>Peminjam,</p>
            <div class="ttd-space"></div>
            <p><b>( <?= e($user_data['nama_lengkap']) ?> )</b></p>
        </div>
        <div class="ttd-box">
            <p>Petugas Sarpras,</p>
            <div class="ttd-space"></div>
            <p><b>( <?= e($nama_petugas) ?> )</b></p>
        </div>
    </div>
</div>

<!-- Tombol Aksi di Bawah (Kembali ke Pengembalian di sebelah kiri tombol cetak) -->
<div class="action-bottom-container">
    <a href="pengembalian.php" class="btn-action btn-back"><i class="fa-solid fa-arrow-left"></i> Kembali ke Pengembalian</a>
    <button onclick="window.print()" class="btn-action btn-print"><i class="fa-solid fa-print"></i> Cetak Bukti Peminjaman</button>
</div>

</body>
</html>
<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("petugas");
$aktif=q("SELECT v.*,DATEDIFF(CURDATE(),v.tanggal_kembali_rencana) telat FROM v_peminjaman v WHERE v.status='Disetujui' ORDER BY v.tanggal_kembali_rencana LIMIT 100")->fetch_all(MYSQLI_ASSOC);
$done=q("SELECT r.*,v.nama_lengkap,v.nama_barang FROM pengembalian r JOIN v_peminjaman v USING(id_peminjaman) ORDER BY r.id_pengembalian DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
top('Pantau pengembalian'); ?>
<div class="card"><h3>Belum dikembalikan</h3><div class="tw"><table><tr><th>#</th><th>Peminjam</th><th>Sarpras</th><th>Jml</th><th>Batas kembali</th><th>Keterangan</th></tr>
<?php foreach($aktif as $r): ?><tr><td><?= $r['id_peminjaman'] ?></td><td><b><?= e($r['nama_lengkap']) ?></b></td><td><?= e($r['nama_barang']) ?></td><td><?= $r['jumlah_pinjam'] ?></td><td><?= e($r['tanggal_kembali_rencana']) ?></td>
<td><?= $r['telat']>0 ? '<span class="pill bad">Terlambat '.$r['telat'].' hari · denda '.rupiah($r['telat']*5000).'</span>' : '<span class="pill good">Masih dalam batas</span>' ?></td></tr><?php endforeach; if(!$aktif) echo '<tr><td colspan="6" class="muted">Tidak ada barang yang sedang dipinjam.</td></tr>'; ?>
</table></div></div>
<div class="card"><h3>Pengembalian terbaru</h3><div class="tw"><table><tr><th>Peminjam</th><th>Sarpras</th><th>Tanggal</th><th>Kondisi</th><th>Denda</th></tr>
<?php foreach($done as $r): ?><tr><td><b><?= e($r['nama_lengkap']) ?></b></td><td><?= e($r['nama_barang']) ?></td><td><?= e($r['tanggal_dikembalikan']) ?></td><td><?= badge($r['kondisi_pulang']) ?></td><td><?= rupiah($r['denda']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

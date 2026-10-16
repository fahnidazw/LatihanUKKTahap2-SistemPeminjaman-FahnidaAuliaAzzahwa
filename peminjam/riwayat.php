<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("peminjam");
$uid=$_SESSION['u']['id_user'];
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $id=(int)$_POST['id'];
 $own=q("SELECT 1 FROM peminjaman WHERE id_peminjaman=? AND id_user=? AND status='Disetujui'",'ii',$id,$uid)->num_rows;
 if ($own && q("INSERT INTO pengembalian(id_peminjaman,tanggal_dikembalikan,kondisi_pulang,catatan) VALUES(?,CURDATE(),?,?)",'iss',$id,$_POST['kondisi'],trim($_POST['catatan']))) {
  $d=q("SELECT denda FROM pengembalian WHERE id_peminjaman=?",'i',$id)->fetch_assoc()['denda'];
  flash('Pengembalian tercatat. Denda: '.rupiah($d)); logact('Mengembalikan peminjaman #'.$id);
 } else flash('Pengembalian gagal dicatat','bad');
 go();
}
$rows=q("SELECT v.*,r.tanggal_dikembalikan,r.kondisi_pulang,r.denda,DATEDIFF(CURDATE(),v.tanggal_kembali_rencana) telat FROM v_peminjaman v LEFT JOIN pengembalian r USING(id_peminjaman) WHERE v.id_user=? ORDER BY v.id_peminjaman DESC LIMIT 50",'i',$uid)->fetch_all(MYSQLI_ASSOC);
top('Riwayat & pengembalian'); ?>
<div class="card"><div class="tw"><table><tr><th>#</th><th>Sarpras</th><th>Jml</th><th>Tanggal pinjam</th><th>Batas kembali</th><th>Status</th><th>Keterangan</th></tr>
<?php foreach($rows as $r): ?><tr><td><?= $r['id_peminjaman'] ?></td><td><b><?= e($r['nama_barang']) ?></b><br><small><?= e($r['keperluan']) ?></small></td><td><?= $r['jumlah_pinjam'] ?></td><td><?= e($r['tanggal_pinjam']) ?></td><td><?= e($r['tanggal_kembali_rencana']) ?></td><td><?= badge($r['status']) ?></td>
<td><?php if($r['status']==='Disetujui'): ?>
<?= $r['telat']>0 ? '<span class="pill bad">Terlambat '.$r['telat'].' hari</span><br>' : '' ?>
<button class="btn sm" onclick="kembali(<?= $r['id_peminjaman'] ?>,'<?= e(addslashes($r['nama_barang'])) ?>')">Kembalikan</button>
<?php elseif($r['status']==='Dikembalikan'): ?>Kembali <?= e($r['tanggal_dikembalikan']) ?> · <?= badge($r['kondisi_pulang']) ?> · Denda <b><?= rupiah($r['denda']) ?></b>
<?php elseif($r['status']==='Pending'): ?><small>Menunggu petugas</small><?php else: ?><small>Pengajuan ditolak</small><?php endif; ?></td></tr>
<?php endforeach; if(!$rows) echo '<tr><td colspan="7" class="muted">Belum ada peminjaman. Buka Katalog sarpras untuk mengajukan.</td></tr>'; ?>
</table></div></div>
<dialog id="dlg"><form method="post"><h3 id="jd">Kembalikan sarpras</h3><input type="hidden" name="id" id="ib">
<p class="muted">Serahkan barang ke ruang Sarpras. Denda dihitung otomatis jika terlambat, rusak, atau hilang.</p>
<label>Kondisi saat dikembalikan<select name="kondisi"><option>Baik</option><option>Rusak</option><option>Hilang</option></select></label>
<label>Catatan<textarea name="catatan" rows="2"></textarea></label>
<div class="act"><button class="btn">Konfirmasi pengembalian</button><button type="button" class="btn ghost" onclick="dlg.close()">Batal</button></div></form></dialog>
<script>function kembali(i,n){ib.value=i;jd.textContent='Kembalikan '+n;dlg.showModal()}</script>
<?php bottom();

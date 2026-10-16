<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $id=(int)($_POST['id']??0);
 if ($_POST['a']==='new') {
  if (q("INSERT INTO pengembalian(id_peminjaman,tanggal_dikembalikan,kondisi_pulang,catatan) VALUES(?,?,?,?)",'isss',(int)$_POST['pinjam'],$_POST['tgl'],$_POST['kondisi'],trim($_POST['catatan']))) { flash('Pengembalian dicatat. Denda dihitung otomatis dan stok diperbarui.'); logact('Admin mencatat pengembalian peminjaman #'.(int)$_POST['pinjam']); }
  else flash('Gagal mencatat pengembalian','bad');
 } elseif ($_POST['a']==='edit') {
  if (q("UPDATE pengembalian SET catatan=? WHERE id_pengembalian=?",'si',trim($_POST['catatan']),$id)) { flash('Catatan diperbarui'); logact('Admin mengubah pengembalian #'.$id); }
 } else {
  if (q("DELETE FROM pengembalian WHERE id_pengembalian=?",'i',$id)) { flash('Pengembalian dibatalkan, peminjaman kembali berstatus Disetujui dan stok disesuaikan'); logact('Admin menghapus pengembalian #'.$id); }
 }
 go();
}
$e=null;
if (isset($_GET['edit'])) $e=q("SELECT * FROM pengembalian WHERE id_pengembalian=?",'i',(int)$_GET['edit'])->fetch_assoc();
$open=q("SELECT id_peminjaman,nama_lengkap,nama_barang FROM v_peminjaman WHERE status='Disetujui' ORDER BY id_peminjaman")->fetch_all(MYSQLI_ASSOC);
$rows=q("SELECT r.*,v.nama_lengkap,v.nama_barang FROM pengembalian r JOIN v_peminjaman v USING(id_peminjaman) ORDER BY r.id_pengembalian DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
top('Pengembalian'); ?>
<div class="card"><h3><?= $e?'Edit catatan pengembalian':'Catat pengembalian' ?></h3>
<form method="post" class="form"><input type="hidden" name="a" value="<?= $e?'edit':'new' ?>"><input type="hidden" name="id" value="<?= $e['id_pengembalian']??0 ?>">
<?php if(!$e): ?>
<label>Peminjaman aktif<select name="pinjam" required><?php foreach($open as $o): ?><option value="<?= $o['id_peminjaman'] ?>">#<?= $o['id_peminjaman'] ?> · <?= e($o['nama_lengkap']) ?> · <?= e($o['nama_barang']) ?></option><?php endforeach; ?></select></label>
<label>Tanggal dikembalikan<input type="date" name="tgl" value="<?= date('Y-m-d') ?>" required></label>
<label>Kondisi saat kembali<select name="kondisi"><option>Baik</option><option>Rusak</option><option>Hilang</option></select></label>
<?php endif; ?>
<label>Catatan petugas<input name="catatan" value="<?= e($e['catatan']??'') ?>"></label>
<div class="act"><button class="btn">Simpan</button><?php if($e): ?><a class="btn ghost" href="pengembalian.php">Batal</a><?php endif; ?></div></form></div>
<div class="card"><div class="tw"><table><tr><th>#</th><th>Peminjam</th><th>Sarpras</th><th>Dikembalikan</th><th>Kondisi</th><th>Denda</th><th>Catatan</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><?= $r['id_peminjaman'] ?></td><td><b><?= e($r['nama_lengkap']) ?></b></td><td><?= e($r['nama_barang']) ?></td><td><?= e($r['tanggal_dikembalikan']) ?></td><td><?= badge($r['kondisi_pulang']) ?></td><td><b><?= rupiah($r['denda']) ?></b></td><td><?= e($r['catatan']) ?></td>
<td><div class="act"><a class="btn sm ghost" href="?edit=<?= $r['id_pengembalian'] ?>">Edit</a><form method="post" onsubmit="return confirm('Batalkan pengembalian ini?')"><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $r['id_pengembalian'] ?>"><button class="btn sm red">Hapus</button></form></div></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

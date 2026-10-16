<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $a=$_POST['a']; $id=(int)($_POST['id']??0);
 if ($a==='new') {
  $b=(int)$_POST['barang']; $j=max(1,(int)$_POST['jumlah']); $tp=$_POST['tp']; $tk=$_POST['tk'];
  $st=q("SELECT stok FROM inventaris WHERE id_barang=?",'i',$b)->fetch_assoc();
  if (!$st || $j>$st['stok'] || $tk<$tp) flash('Jumlah melebihi stok atau tanggal tidak valid','bad');
  else {
   $db->begin_transaction();
   $ok=q("INSERT INTO peminjaman(id_user,tanggal_pinjam,tanggal_kembali_rencana,keperluan) VALUES(?,?,?,?)",'isss',(int)$_POST['user'],$tp,$tk,trim($_POST['keperluan']));
   $pid=$db->insert_id;
   $ok=$ok && q("INSERT INTO detail_peminjaman(id_peminjaman,id_barang,jumlah_pinjam) VALUES(?,?,?)",'iii',$pid,$b,$j);
   if ($ok) { $db->commit(); flash('Data peminjaman ditambahkan (status Pending)'); logact('Admin menambah peminjaman #'.$pid); } else { $db->rollback(); flash('Gagal menyimpan peminjaman','bad'); }
  }
 } elseif ($a==='edit') {
  if (q("UPDATE peminjaman SET tanggal_pinjam=?,tanggal_kembali_rencana=?,keperluan=? WHERE id_peminjaman=?",'sssi',$_POST['tp'],$_POST['tk'],trim($_POST['keperluan']),$id)) { flash('Data peminjaman diperbarui'); logact('Admin mengubah peminjaman #'.$id); }
 } elseif ($a==='del') {
  q("DELETE FROM peminjaman WHERE id_peminjaman=? AND status<>'Disetujui'",'i',$id);
  if ($db->affected_rows>0) { flash('Data peminjaman dihapus'); logact('Admin menghapus peminjaman #'.$id); } else flash('Peminjaman yang sedang berjalan tidak bisa dihapus. Selesaikan pengembaliannya dulu.','bad');
 }
 go();
}
$e=null;
if (isset($_GET['edit'])) $e=q("SELECT * FROM peminjaman WHERE id_peminjaman=?",'i',(int)$_GET['edit'])->fetch_assoc();
$users=q("SELECT id_user,nama_lengkap FROM users WHERE level='peminjam' ORDER BY nama_lengkap")->fetch_all(MYSQLI_ASSOC);
$items=q("SELECT id_barang,nama_barang,stok FROM inventaris WHERE stok>0 AND kondisi<>'Rusak' ORDER BY nama_barang")->fetch_all(MYSQLI_ASSOC);
$rows=q("SELECT * FROM v_peminjaman ORDER BY id_peminjaman DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
top('Peminjaman'); ?>
<div class="card"><h3><?= $e?'Edit peminjaman #'.$e['id_peminjaman']:'Tambah peminjaman' ?></h3>
<form method="post" class="form"><input type="hidden" name="a" value="<?= $e?'edit':'new' ?>"><input type="hidden" name="id" value="<?= $e['id_peminjaman']??0 ?>">
<?php if(!$e): ?>
<label>Peminjam<select name="user"><?php foreach($users as $u): ?><option value="<?= $u['id_user'] ?>"><?= e($u['nama_lengkap']) ?></option><?php endforeach; ?></select></label>
<label>Sarpras<select name="barang"><?php foreach($items as $i): ?><option value="<?= $i['id_barang'] ?>"><?= e($i['nama_barang']) ?> (stok <?= $i['stok'] ?>)</option><?php endforeach; ?></select></label>
<label>Jumlah<input type="number" name="jumlah" min="1" value="1" required></label>
<?php endif; ?>
<label>Tanggal pinjam<input type="date" name="tp" required value="<?= $e['tanggal_pinjam']??date('Y-m-d') ?>"></label>
<label>Rencana kembali<input type="date" name="tk" required value="<?= $e['tanggal_kembali_rencana']??date('Y-m-d',strtotime('+3 days')) ?>"></label>
<label>Keperluan<input name="keperluan" required value="<?= e($e['keperluan']??'') ?>"></label>
<div class="act"><button class="btn">Simpan</button><?php if($e): ?><a class="btn ghost" href="peminjaman.php">Batal</a><?php endif; ?></div></form></div>
<div class="card"><div class="tw"><table><tr><th>#</th><th>Peminjam</th><th>Sarpras</th><th>Jml</th><th>Pinjam</th><th>Kembali</th><th>Status</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><?= $r['id_peminjaman'] ?></td><td><b><?= e($r['nama_lengkap']) ?></b><br><small><?= e($r['kelas_jabatan']) ?></small></td><td><?= e($r['nama_barang']) ?></td><td><?= $r['jumlah_pinjam'] ?></td><td><?= e($r['tanggal_pinjam']) ?></td><td><?= e($r['tanggal_kembali_rencana']) ?></td><td><?= badge($r['status']) ?></td>
<td><div class="act"><a class="btn sm ghost" href="?edit=<?= $r['id_peminjaman'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus data ini?')"><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $r['id_peminjaman'] ?>"><button class="btn sm red">Hapus</button></form></div></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $id=(int)($_POST['id']??0);
 if ($_POST['a']==='save') {
  $n=trim($_POST['nama']);
  $ok=$id? q("UPDATE kategori SET nama_kategori=? WHERE id_kategori=?",'si',$n,$id) : q("INSERT INTO kategori(nama_kategori) VALUES(?)",'s',$n);
  if ($ok) { flash('Kategori tersimpan'); logact('Menyimpan kategori '.$n); } else flash('Gagal, nama kategori sudah ada','bad');
 } else {
  if (q("DELETE FROM kategori WHERE id_kategori=?",'i',$id)) { flash('Kategori dihapus'); logact('Menghapus kategori #'.$id); } else flash('Kategori masih dipakai oleh data sarpras','bad');
 }
 go();
}
$e=['id_kategori'=>0,'nama_kategori'=>''];
if (isset($_GET['edit'])) $e=q("SELECT * FROM kategori WHERE id_kategori=?",'i',(int)$_GET['edit'])->fetch_assoc()?:$e;
$rows=q("SELECT k.*,COUNT(b.id_barang) jml FROM kategori k LEFT JOIN inventaris b USING(id_kategori) GROUP BY k.id_kategori ORDER BY nama_kategori")->fetch_all(MYSQLI_ASSOC);
top('Kategori'); ?>
<div class="card"><form method="post" class="form"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= $e['id_kategori'] ?>">
<label>Nama kategori<input name="nama" required value="<?= e($e['nama_kategori']) ?>"></label>
<div class="act"><button class="btn">Simpan kategori</button><?php if($e['id_kategori']): ?><a class="btn ghost" href="kategori.php">Batal</a><?php endif; ?></div></form></div>
<div class="card"><div class="tw"><table><tr><th>Kategori</th><th>Jumlah sarpras</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><b><?= e($r['nama_kategori']) ?></b></td><td><?= $r['jml'] ?></td><td><div class="act"><a class="btn sm ghost" href="?edit=<?= $r['id_kategori'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus kategori ini?')"><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $r['id_kategori'] ?>"><button class="btn sm red">Hapus</button></form></div></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

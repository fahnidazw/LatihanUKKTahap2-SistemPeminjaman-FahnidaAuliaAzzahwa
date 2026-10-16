<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $id=(int)($_POST['id']??0);
 if ($_POST['a']==='save') {
  $foto=upload_foto('foto',$_POST['foto_lama']??'');
  $f=[(int)$_POST['kat'],trim($_POST['nama']),trim($_POST['kode']),max(0,(int)$_POST['stok']),$_POST['kondisi'],$foto];
  $ok=$id? q("UPDATE inventaris SET id_kategori=?,nama_barang=?,kode_inventaris=?,stok=?,kondisi=?,foto=? WHERE id_barang=?",'ississi',...array_merge($f,[$id]))
         : q("INSERT INTO inventaris(id_kategori,nama_barang,kode_inventaris,stok,kondisi,foto) VALUES(?,?,?,?,?,?)",'ississ',...$f);
  if ($ok) { flash('Data sarpras tersimpan'); logact('Menyimpan sarpras '.$f[1]); } else flash('Gagal, kode inventaris sudah dipakai','bad');
 } else {
  if (q("DELETE FROM inventaris WHERE id_barang=?",'i',$id)) { flash('Sarpras dihapus'); logact('Menghapus sarpras #'.$id); } else flash('Sarpras tidak bisa dihapus karena sudah ada di riwayat peminjaman','bad');
 }
 go();
}
$e=['id_barang'=>0,'id_kategori'=>0,'nama_barang'=>'','kode_inventaris'=>'','stok'=>1,'kondisi'=>'Baik','foto'=>''];
if (isset($_GET['edit'])) $e=q("SELECT * FROM inventaris WHERE id_barang=?",'i',(int)$_GET['edit'])->fetch_assoc()?:$e;
$kats=q("SELECT * FROM kategori ORDER BY nama_kategori")->fetch_all(MYSQLI_ASSOC);
$c='%'.($_GET['cari']??'').'%';
$rows=q("SELECT b.*,k.nama_kategori FROM inventaris b JOIN kategori k USING(id_kategori) WHERE b.nama_barang LIKE ? OR b.kode_inventaris LIKE ? ORDER BY k.nama_kategori,b.nama_barang LIMIT 100",'ss',$c,$c)->fetch_all(MYSQLI_ASSOC);
top('Sarpras'); ?>
<div class="card"><h3><?= $e['id_barang']?'Edit sarpras':'Tambah sarpras' ?></h3>
<form method="post" enctype="multipart/form-data" class="form"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= $e['id_barang'] ?>"><input type="hidden" name="foto_lama" value="<?= e($e['foto']) ?>">
<label>Nama barang / ruangan<input name="nama" required value="<?= e($e['nama_barang']) ?>"></label>
<label>Kategori<select name="kat"><?php foreach($kats as $k): ?><option value="<?= $k['id_kategori'] ?>" <?= $e['id_kategori']==$k['id_kategori']?'selected':'' ?>><?= e($k['nama_kategori']) ?></option><?php endforeach; ?></select></label>
<label>Kode inventaris<input name="kode" required value="<?= e($e['kode_inventaris']) ?>"></label>
<label>Stok tersedia<input type="number" min="0" name="stok" required value="<?= $e['stok'] ?>"></label>
<label>Kondisi<select name="kondisi"><?php foreach(['Baik','Perlu Perbaikan','Rusak'] as $k): ?><option <?= $e['kondisi']===$k?'selected':'' ?>><?= $k ?></option><?php endforeach; ?></select></label>
<label>Foto (JPG/PNG/WEBP, maks 2 MB)<input type="file" name="foto" accept="image/*"></label>
<div class="act"><button class="btn">Simpan sarpras</button><?php if($e['id_barang']): ?><a class="btn ghost" href="alat.php">Batal</a><?php endif; ?></div></form></div>
<form class="tools"><input name="cari" placeholder="Cari nama atau kode..." value="<?= e($_GET['cari']??'') ?>"><button class="btn ghost">Cari</button></form>
<div class="card"><div class="tw"><table><tr><th></th><th>Nama</th><th>Kategori</th><th>Kode</th><th>Stok</th><th>Kondisi</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><div class="thumb"><?= foto($r['foto'],$r['nama_barang']) ?></div></td><td><b><?= e($r['nama_barang']) ?></b></td><td><?= e($r['nama_kategori']) ?></td><td><?= e($r['kode_inventaris']) ?></td><td><?= $r['stok'] ?></td><td><?= badge($r['kondisi']) ?></td>
<td><div class="act"><a class="btn sm ghost" href="?edit=<?= $r['id_barang'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus sarpras ini?')"><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $r['id_barang'] ?>"><button class="btn sm red">Hapus</button></form></div></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

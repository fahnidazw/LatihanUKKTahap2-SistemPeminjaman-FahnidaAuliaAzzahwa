<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("peminjam");
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $id=(int)$_POST['id_barang']; $j=max(1,(int)$_POST['jumlah']); $tp=$_POST['tp']; $tk=$_POST['tk']; $kep=trim($_POST['keperluan']);
 $b=q("SELECT stok,kondisi,nama_barang FROM inventaris WHERE id_barang=?",'i',$id)->fetch_assoc();
 if (!$b || $b['kondisi']==='Rusak' || $j>$b['stok']) flash('Jumlah melebihi stok atau barang sedang tidak bisa dipinjam','bad');
 elseif ($tk<$tp || $tp<date('Y-m-d') || $kep==='') flash('Periksa tanggal pinjam, tanggal kembali, dan keperluan','bad');
 else {
  $db->begin_transaction();
  $ok=q("INSERT INTO peminjaman(id_user,tanggal_pinjam,tanggal_kembali_rencana,keperluan) VALUES(?,?,?,?)",'isss',$_SESSION['u']['id_user'],$tp,$tk,$kep);
  $pid=$db->insert_id;
  $ok=$ok && q("INSERT INTO detail_peminjaman(id_peminjaman,id_barang,jumlah_pinjam) VALUES(?,?,?)",'iii',$pid,$id,$j);
  if ($ok) { $db->commit(); flash('Pengajuan terkirim. Tunggu persetujuan petugas di menu Riwayat.'); logact('Mengajukan peminjaman '.$b['nama_barang']); }
  else { $db->rollback(); flash('Pengajuan gagal dikirim, coba lagi','bad'); }
 }
 go();
}
$k=(int)($_GET['k']??0); $c='%'.($_GET['cari']??'').'%';
$kats=q("SELECT * FROM kategori ORDER BY nama_kategori")->fetch_all(MYSQLI_ASSOC);
$rows=q("SELECT b.*,k.nama_kategori FROM inventaris b JOIN kategori k USING(id_kategori) WHERE (?=0 OR b.id_kategori=?) AND (b.nama_barang LIKE ? OR b.kode_inventaris LIKE ?) ORDER BY b.nama_barang LIMIT 60",'iiss',$k,$k,$c,$c)->fetch_all(MYSQLI_ASSOC);
top('Katalog sarpras'); ?>
<div class="cats"><a href="index.php" class="<?= !$k?'on':'' ?>">Semua</a><?php foreach($kats as $x): ?><a href="?k=<?= $x['id_kategori'] ?>" class="<?= $k==$x['id_kategori']?'on':'' ?>"><?= e($x['nama_kategori']) ?></a><?php endforeach; ?></div>
<form class="tools"><input type="hidden" name="k" value="<?= $k ?>"><input name="cari" placeholder="Cari proyektor, aula, lab..." value="<?= e($_GET['cari']??'') ?>"><button class="btn ghost">Cari</button></form>
<div class="catalog">
<?php foreach($rows as $r): $ok=$r['stok']>0 && $r['kondisi']!=='Rusak'; ?>
<article class="item"><div class="pic"><?= foto($r['foto'],$r['nama_barang']) ?></div><div class="in">
<small><?= e($r['nama_kategori']) ?> · <?= e($r['kode_inventaris']) ?></small><h4><?= e($r['nama_barang']) ?></h4><div><?= badge($r['kondisi']) ?></div>
<div class="row"><span class="stok <?= $ok?'':'zero' ?>">Stok <?= $r['stok'] ?></span>
<?php if($ok): ?><button class="btn sm" onclick="pinjam(<?= $r['id_barang'] ?>,'<?= e(addslashes($r['nama_barang'])) ?>',<?= $r['stok'] ?>)">Ajukan pinjam</button><?php else: ?><button class="btn sm ghost" disabled>Tidak tersedia</button><?php endif; ?></div></div></article>
<?php endforeach; if(!$rows) echo '<p class="muted">Tidak ada sarpras yang cocok. Coba kata kunci atau kategori lain.</p>'; ?>
</div>
<dialog id="dlg"><form method="post"><h3 id="jd">Ajukan peminjaman</h3><input type="hidden" name="id_barang" id="ib">
<label>Jumlah<input type="number" name="jumlah" id="jm" min="1" value="1" required></label>
<label>Tanggal pinjam<input type="date" name="tp" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required></label>
<label>Rencana tanggal kembali<input type="date" name="tk" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d',strtotime('+1 day')) ?>" required></label>
<label>Keperluan<textarea name="keperluan" rows="3" required placeholder="Contoh: praktik mapel produktif RPL"></textarea></label>
<div class="act"><button class="btn">Kirim pengajuan</button><button type="button" class="btn ghost" onclick="dlg.close()">Batal</button></div></form></dialog>
<script>function pinjam(i,n,s){ib.value=i;jd.textContent='Pinjam '+n;jm.max=s;jm.value=1;dlg.showModal()}</script>
<?php bottom();

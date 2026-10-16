<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
if ($_SERVER['REQUEST_METHOD']==='POST') {
 $a=$_POST['a'];
 if ($a==='save') {
  $id=(int)$_POST['id']; $pw=$_POST['password'];
  $f=[trim($_POST['nama']),trim($_POST['username']),$_POST['level'],trim($_POST['nis']),trim($_POST['kelas']),trim($_POST['telp'])];
  if ($id) {
   if ($pw!=='') $ok=q("UPDATE users SET nama_lengkap=?,username=?,level=?,nis_nip=?,kelas_jabatan=?,no_telp=?,password=? WHERE id_user=?",'sssssssi',...array_merge($f,[password_hash($pw,PASSWORD_DEFAULT),$id]));
   else $ok=q("UPDATE users SET nama_lengkap=?,username=?,level=?,nis_nip=?,kelas_jabatan=?,no_telp=? WHERE id_user=?",'ssssssi',...array_merge($f,[$id]));
  } else {
   $ok=$pw!=='' && q("INSERT INTO users(nama_lengkap,username,level,nis_nip,kelas_jabatan,no_telp,password) VALUES(?,?,?,?,?,?,?)",'sssssss',...array_merge($f,[password_hash($pw,PASSWORD_DEFAULT)]));
  }
  $ok ? (flash('Data pengguna tersimpan') && 0) : flash('Gagal menyimpan. Username mungkin sudah dipakai atau password kosong.','bad');
  if ($ok) logact('Menyimpan pengguna '.$f[1]);
 } elseif ($a==='del') {
  $id=(int)$_POST['id'];
  if ($id===$_SESSION['u']['id_user']) flash('Akun yang sedang dipakai tidak bisa dihapus','bad');
  elseif (q("DELETE FROM users WHERE id_user=?",'i',$id)) { flash('Pengguna dihapus'); logact('Menghapus pengguna #'.$id); }
  else flash('Pengguna tidak bisa dihapus karena masih punya riwayat peminjaman','bad');
 }
 go();
}
$e=['id_user'=>0,'nama_lengkap'=>'','username'=>'','level'=>'peminjam','nis_nip'=>'','kelas_jabatan'=>'','no_telp'=>''];
if (isset($_GET['edit'])) $e=q("SELECT * FROM users WHERE id_user=?",'i',(int)$_GET['edit'])->fetch_assoc()?:$e;
$rows=q("SELECT * FROM users ORDER BY level,nama_lengkap LIMIT 200")->fetch_all(MYSQLI_ASSOC);
top('Pengguna'); ?>
<div class="card"><h3><?= $e['id_user']?'Edit pengguna':'Tambah pengguna' ?></h3>
<form method="post" class="form"><input type="hidden" name="a" value="save"><input type="hidden" name="id" value="<?= $e['id_user'] ?>">
<label>Nama lengkap<input name="nama" required value="<?= e($e['nama_lengkap']) ?>"></label>
<label>Username<input name="username" required value="<?= e($e['username']) ?>"></label>
<label>Password<input type="password" name="password" placeholder="<?= $e['id_user']?'Kosongkan jika tidak diganti':'' ?>" <?= $e['id_user']?'':'required' ?>></label>
<label>Level<select name="level"><?php foreach(['admin','petugas','peminjam'] as $l): ?><option <?= $e['level']===$l?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></label>
<label>NIS / NIP<input name="nis" value="<?= e($e['nis_nip']) ?>"></label>
<label>Kelas / Jabatan<input name="kelas" value="<?= e($e['kelas_jabatan']) ?>"></label>
<label>No. telepon<input name="telp" value="<?= e($e['no_telp']) ?>"></label>
<div class="act"><button class="btn">Simpan pengguna</button><?php if($e['id_user']): ?><a class="btn ghost" href="user.php">Batal</a><?php endif; ?></div></form></div>
<div class="card"><div class="tw"><table><tr><th>Nama</th><th>Username</th><th>Level</th><th>NIS/NIP</th><th>Kelas/Jabatan</th><th></th></tr>
<?php foreach($rows as $r): ?><tr><td><b><?= e($r['nama_lengkap']) ?></b></td><td><?= e($r['username']) ?></td><td><span class="pill <?= $r['level']==='admin'?'bad':($r['level']==='petugas'?'warn':'good') ?>"><?= e($r['level']) ?></span></td><td><?= e($r['nis_nip']) ?></td><td><?= e($r['kelas_jabatan']) ?></td>
<td><div class="act"><a class="btn sm ghost" href="?edit=<?= $r['id_user'] ?>">Edit</a><form method="post" onsubmit="return confirm('Hapus pengguna ini?')"><input type="hidden" name="a" value="del"><input type="hidden" name="id" value="<?= $r['id_user'] ?>"><button class="btn sm red">Hapus</button></form></div></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

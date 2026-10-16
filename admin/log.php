<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
$rows=q("SELECT l.*,u.nama_lengkap,u.level FROM log_aktivitas l LEFT JOIN users u ON u.id_user=l.id_user ORDER BY l.id_log DESC LIMIT 200")->fetch_all(MYSQLI_ASSOC);
top('Log aktivitas'); ?>
<div class="card"><p class="muted">200 aktivitas terakhir dari semua pengguna.</p><div class="tw"><table><tr><th>Waktu</th><th>Pengguna</th><th>Level</th><th>Aktivitas</th></tr>
<?php foreach($rows as $l): ?><tr><td><?= e($l['waktu']) ?></td><td><b><?= e($l['nama_lengkap']??'-') ?></b></td><td><?= e($l['level']??'-') ?></td><td><?= e($l['aksi']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

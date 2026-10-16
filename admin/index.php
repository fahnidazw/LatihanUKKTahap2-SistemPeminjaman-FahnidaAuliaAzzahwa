<?php require __DIR__."/../koneksi.php"; require __DIR__."/../asset/layout.php"; auth("admin");
$s = q("SELECT (SELECT COUNT(*) FROM inventaris) a,(SELECT COUNT(*) FROM users) b,(SELECT COUNT(*) FROM peminjaman WHERE status='Pending') c,(SELECT COUNT(*) FROM peminjaman WHERE status='Disetujui') d,(SELECT COALESCE(SUM(denda),0) FROM pengembalian) e")->fetch_assoc();
$log = q("SELECT l.*,u.nama_lengkap FROM log_aktivitas l LEFT JOIN users u ON u.id_user=l.id_user ORDER BY l.id_log DESC LIMIT 8")->fetch_all(MYSQLI_ASSOC);
top('Dashboard');
echo '<div class="stats">'.stat('Jenis sarpras',$s['a']).stat('Pengguna',$s['b']).stat('Menunggu persetujuan',$s['c'],'sun').stat('Sedang dipinjam',$s['d'],'hot').stat('Total denda',rupiah($s['e'])).'</div>';
?>
<div class="card"><h3>Aktivitas terbaru</h3><div class="tw"><table>
<tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th></tr>
<?php foreach($log as $l): ?><tr><td><?= e($l['waktu']) ?></td><td><?= e($l['nama_lengkap'] ?? '-') ?></td><td><?= e($l['aksi']) ?></td></tr><?php endforeach; ?>
</table></div></div>
<?php bottom();

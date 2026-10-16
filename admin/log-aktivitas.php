<?php
// admin/log-aktivitas.php - Log aktivitas sistem (khusus Admin)
// Fitur: statistik, filter (kata kunci, pengguna, jenis, tanggal), paginasi, unduh CSV (hanya baca, log tidak bisa dihapus)
require __DIR__ . '/../koneksi.php';
auth('admin');

/* ---------- fungsi bantu tampilan ---------- */
function waktu_lalu($ts) {
    $d = time() - strtotime($ts);
    if ($d < 60) return 'baru saja';
    if ($d < 3600) return floor($d / 60) . ' menit lalu';
    if ($d < 86400) return floor($d / 3600) . ' jam lalu';
    if ($d < 604800) return floor($d / 86400) . ' hari lalu';
    return date('d M Y', strtotime($ts));
}
function ikon_log($aksi) {
    $map = [
        ['Login', 'fa-right-to-bracket', 'in'], ['Logout', 'fa-right-from-bracket', 'out'],
        ['Menyetujui', 'fa-circle-check', 'ok'], ['Menolak', 'fa-circle-xmark', 'bad'],
        ['Mengajukan', 'fa-paper-plane', 'info'], ['Mengembalikan', 'fa-arrow-rotate-left', 'ok'],
        ['Admin mencatat', 'fa-arrow-rotate-left', 'ok'], ['Menghapus', 'fa-trash', 'bad'],
        ['Admin menghapus', 'fa-trash', 'bad'], ['Menyimpan', 'fa-floppy-disk', 'info'],
        ['Admin menambah', 'fa-floppy-disk', 'info'], ['Admin mengubah', 'fa-pen', 'info'],
        ['Mengunduh', 'fa-file-arrow-down', 'info'],
    ];
    foreach ($map as [$k, $i, $c]) if (stripos($aksi, $k) === 0) return [$i, $c];
    return ['fa-circle-info', 'info'];
}

/* ---------- filter ---------- */
$cari  = trim($_GET['cari'] ?? '');
$uid   = (int)($_GET['user'] ?? 0);
$f     = $_GET['f'] ?? '';
$hari  = $_GET['hari'] ?? '';
$d1    = $_GET['dari'] ?? '';
$d2    = $_GET['sampai'] ?? '';
// chip filter: kunci => [label, ikon, kata kunci pada kolom aksi]
$chipMap = [
    'login'   => ['Login',   'fa-right-to-bracket',  ['Login']],
    'logout'  => ['Logout',  'fa-right-from-bracket', ['Logout']],
    'ajukan'  => ['Ajukan',  'fa-circle-plus',        ['Mengajukan']],
    'setuju'  => ['Setujui', 'fa-circle-check',       ['Menyetujui']],
    'tolak'   => ['Tolak',   'fa-circle-xmark',       ['Menolak']],
    'kembali' => ['Kembali', 'fa-arrow-rotate-left',  ['embali']],
    'ubah'    => ['Ubah',    'fa-pen-to-square',      ['Menyimpan', 'mengubah', 'menambah']],
    'hapus'   => ['Hapus',   'fa-trash',              ['hapus']],
];

$w = ['1=1']; $t = ''; $p = [];
if ($cari !== '') { $w[] = '(l.aksi LIKE ? OR u.nama_lengkap LIKE ?)'; $t .= 'ss'; $p[] = "%$cari%"; $p[] = "%$cari%"; }
if ($uid)         { $w[] = 'l.id_user=?';   $t .= 'i'; $p[] = $uid; }
if (isset($chipMap[$f])) {
    $or = [];
    foreach ($chipMap[$f][2] as $k) { $or[] = 'l.aksi LIKE ?'; $t .= 's'; $p[] = "%$k%"; }
    $w[] = '(' . implode(' OR ', $or) . ')';
}
if ($hari === '1') $w[] = 'l.waktu >= CURDATE()';
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d1)) { $w[] = 'l.waktu >= ?'; $t .= 's'; $p[] = "$d1 00:00:00"; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) { $w[] = 'l.waktu < ?';  $t .= 's'; $p[] = date('Y-m-d', strtotime("$d2 +1 day")) . ' 00:00:00'; }
$where = implode(' AND ', $w);

/* ---------- unduh CSV ---------- */
if (isset($_GET['unduh'])) {
    $res = q("SELECT l.waktu, COALESCE(u.nama_lengkap,'-') nama, COALESCE(u.level,'-') level, l.aksi
              FROM log_aktivitas l LEFT JOIN users u ON u.id_user=l.id_user
              WHERE $where ORDER BY l.id_log DESC LIMIT 5000", $t, ...$p);
    logact('Mengunduh log aktivitas (CSV)');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="log-aktivitas-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $o = fopen('php://output', 'w');
    fputcsv($o, ['Waktu', 'Pengguna', 'Level', 'Aktivitas'], ',', '"', '\\');
    while ($r = $res->fetch_assoc()) fputcsv($o, [$r['waktu'], $r['nama'], $r['level'], $r['aksi']], ',', '"', '\\');
    exit;
}

/* ---------- data ---------- */
$s = q("SELECT COUNT(*) total,
               COALESCE(SUM(DATE(waktu)=CURDATE()),0) hari,
               COALESCE(SUM(DATE(waktu)=CURDATE() AND aksi LIKE 'Login%'),0) login_hari,
               COUNT(DISTINCT CASE WHEN waktu >= NOW() - INTERVAL 7 DAY THEN id_user END) aktif
        FROM log_aktivitas")->fetch_assoc();

$per  = 20;
$hal  = max(1, (int)($_GET['hal'] ?? 1));
$cnt  = (int)q("SELECT COUNT(*) c FROM log_aktivitas l LEFT JOIN users u ON u.id_user=l.id_user WHERE $where", $t, ...$p)->fetch_assoc()['c'];
$pages = max(1, (int)ceil($cnt / $per));
$hal  = min($hal, $pages);
$off  = ($hal - 1) * $per;
$rows = q("SELECT l.*, u.nama_lengkap, u.level FROM log_aktivitas l
           LEFT JOIN users u ON u.id_user=l.id_user
           WHERE $where ORDER BY l.id_log DESC LIMIT $per OFFSET $off", $t, ...$p)->fetch_all(MYSQLI_ASSOC);
$users = q("SELECT id_user, nama_lengkap FROM users ORDER BY nama_lengkap LIMIT 300")->fetch_all(MYSQLI_ASSOC);

$qs = function ($extra = []) { return '?' . http_build_query(array_filter(array_merge($_GET, $extra), fn($v) => $v !== '' && $v !== null)); };
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log Aktivitas - Sarpras</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root{--ink:#0D2B2E;--teal:#0F5F58;--teal-d:#0A3A36;--sun:#FFC54D;--sun-soft:#FFE3A1;--sun-deep:#B47A00;--cream:#EAF5F1;--ivory:#FCFFFE;--line:#D3E6E0;--muted:#5F7A78;--serif:'Cormorant Garamond',Georgia,serif;--sans:'Plus Jakarta Sans',system-ui,sans-serif}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);color:var(--ink);background:var(--cream);-webkit-font-smoothing:antialiased}
.main{margin-left:280px;padding:32px 36px 60px;min-width:0}
.head{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:10.5px;font-weight:700;letter-spacing:.22em;text-transform:uppercase;color:var(--sun-deep)}
.eyebrow::before{content:"";width:6px;height:6px;background:var(--sun);transform:rotate(45deg)}
.head h1{font-family:var(--serif);font-size:38px;line-height:1.05;margin-top:6px}
.head p{color:var(--muted);font-size:13.5px;margin-top:4px}
.btn{font:600 13px var(--sans);display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;border:0;cursor:pointer;text-decoration:none;transition:.2s;color:#fff;background:linear-gradient(135deg,#0A4A46,#0F7D70 60%,#17A38D)}
.btn:hover{transform:translateY(-1px);box-shadow:0 10px 20px -10px rgba(10,74,70,.8)}
.btn.ghost{background:#fff;color:var(--teal);border:1px solid var(--line)}
.btn.ghost:hover{background:var(--cream);box-shadow:none}
.btn.red{background:#FFE3DF;color:#A3281A}
.btn:focus-visible,input:focus-visible,select:focus-visible{outline:2px solid var(--sun-deep);outline-offset:2px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:14px;margin-bottom:20px}
.stat{background:#fff;border:1px solid var(--line);border-radius:18px;padding:18px 20px;display:flex;gap:14px;align-items:center}
.stat i{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;font-size:17px;color:var(--teal);background:var(--cream)}
.stat span{display:block;font-size:12px;color:var(--muted)}
.stat b{font-size:26px;letter-spacing:-.02em;line-height:1.1}
.stat.hot{background:var(--ink);border:0;color:#fff}.stat.hot i{background:rgba(255,197,77,.18);color:var(--sun)}.stat.hot span{color:#9FD3CB}
.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:20px;margin-bottom:20px;box-shadow:0 14px 34px -24px rgba(13,43,46,.4)}
.filter{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end}
.filter label{display:flex;flex-direction:column;gap:5px;font-size:10.5px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#3F5C59}
.filter input,.filter select{font:500 13.5px var(--sans);height:42px;padding:0 12px;border:1px solid var(--line);border-radius:11px;background:var(--ivory);color:var(--ink);width:100%}
.filter input:focus,.filter select:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,157,138,.16);outline:none}
.fa-act{display:flex;gap:8px;flex-wrap:wrap}
.flash{padding:12px 16px;border-radius:12px;margin-bottom:16px;font-weight:600;font-size:13.5px}
.flash.ok{background:#D9F5E6;color:#0B6B3A}.flash.bad{background:#FFE0DC;color:#A3281A}
.tw{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13.5px}
th{text-align:left;padding:10px 12px;font-size:11px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);border-bottom:2px solid var(--line);white-space:nowrap}
td{padding:12px;border-bottom:1px solid var(--line);vertical-align:middle}
tr:hover td{background:#FAFDFC}
.aksi{display:flex;align-items:center;gap:12px}
.aksi i{flex:none;width:34px;height:34px;border-radius:10px;display:grid;place-items:center;font-size:13px}
.aksi i.in{background:#DCECFF;color:#1B4F9C}.aksi i.out{background:#EDE7F6;color:#5B3FA0}
.aksi i.ok{background:#D9F5E6;color:#0B6B3A}.aksi i.bad{background:#FFE0DC;color:#A3281A}.aksi i.info{background:#FFF0C9;color:#7A5600}
.who b{display:block}.who small{color:var(--muted)}
.pill{display:inline-block;padding:2px 10px;border-radius:99px;font-size:11px;font-weight:700;text-transform:capitalize}
.pill.admin{background:#FFE0DC;color:#A3281A}.pill.petugas{background:#FFF0C9;color:#7A5600}.pill.peminjam{background:#D9F5E6;color:#0B6B3A}
.wk b{display:block;font-weight:600}.wk small{color:var(--muted)}
.kosong{text-align:center;padding:40px 10px;color:var(--muted)}
.kosong i{font-size:30px;color:#A9CFC5;display:block;margin-bottom:10px}
.pager{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-top:16px;font-size:13px;color:var(--muted)}
.pager nav{display:flex;gap:6px}
.pager a,.pager span.cur{min-width:36px;height:36px;padding:0 10px;display:grid;place-items:center;border-radius:10px;border:1px solid var(--line);background:#fff;color:var(--ink);text-decoration:none;font-weight:600}
.pager a:hover{background:var(--cream)}.pager span.cur{background:var(--ink);color:#fff;border-color:var(--ink)}
.bersih{display:flex;gap:10px;align-items:center;flex-wrap:wrap;font-size:13px;color:var(--muted)}
.bersih select{font:500 13px var(--sans);height:38px;padding:0 10px;border:1px solid var(--line);border-radius:10px}
.fbar{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:16px 20px;border-radius:26px}
.fsearch{position:relative;flex:1 1 320px;max-width:480px;display:flex}
.fsearch input{width:100%;height:50px;padding:0 16px 0 58px;font:500 14px var(--sans);color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:14px}
.fsearch input::placeholder{color:#8AA5A1;font-weight:400}
.fsearch input:focus{border-color:var(--teal);box-shadow:0 0 0 3px rgba(15,157,138,.16);outline:none}
.sbtn{position:absolute;left:0;top:0;bottom:0;width:48px;border:0;background:transparent;color:#4B6360;font-size:15px;cursor:pointer;border-right:1px solid var(--line)}
.chipbar{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.flabel{font-weight:700;font-size:14px;margin-right:4px;color:#3F5C59}
.chip{display:inline-flex;align-items:center;gap:8px;height:42px;padding:0 16px;border-radius:13px;font:600 13.5px var(--sans);text-decoration:none;color:var(--ink);background:#F7F6F1;border:1px solid var(--line);transition:.2s}
.chip i{font-size:13px}
.chip:hover{background:#fff;transform:translateY(-1px);box-shadow:0 8px 16px -10px rgba(13,43,46,.5)}
.chip.on{background:linear-gradient(145deg,#FFD878,#F0B73A);border-color:#E0A526;box-shadow:0 10px 20px -10px rgba(224,165,38,.9)}
.chip:focus-visible{outline:2px solid var(--sun-deep);outline-offset:2px}
.adv{margin:-8px 0 20px;background:#fff;border:1px solid var(--line);border-radius:16px;padding:12px 20px}
.adv summary{cursor:pointer;font-weight:600;font-size:13px;color:var(--teal)}
.adv .filter{margin-top:14px}
@media(max-width:768px){.main{margin-left:72px;padding:20px 14px 50px}.head h1{font-size:30px}}
</style>
</head>
<body>
<?php require __DIR__ . '/../petugas/header.php'; ?>
<main class="main">
 <div class="head">
  <div>
   <span class="eyebrow">Keamanan &amp; Audit</span>
   <h1>Log Aktivitas</h1>
   <p>Rekam jejak semua tindakan pengguna: login, pengajuan, persetujuan, pengembalian, dan perubahan data.</p>
  </div>
  <a class="btn" href="<?= e($qs(['unduh' => 1, 'hal' => null])) ?>"><i class="fa-solid fa-file-arrow-down"></i> Unduh CSV</a>
 </div>

 <div class="stats">
  <div class="stat"><i class="fa-solid fa-list-ul"></i><div><span>Total aktivitas</span><b><?= number_format((int)$s['total'], 0, ',', '.') ?></b></div></div>
  <div class="stat hot"><i class="fa-solid fa-bolt"></i><div><span>Hari ini</span><b><?= (int)$s['hari'] ?></b></div></div>
  <div class="stat"><i class="fa-solid fa-right-to-bracket"></i><div><span>Login hari ini</span><b><?= (int)$s['login_hari'] ?></b></div></div>
  <div class="stat"><i class="fa-solid fa-users"></i><div><span>Pengguna aktif (7 hari)</span><b><?= (int)$s['aktif'] ?></b></div></div>
 </div>

 <div class="card fbar">
  <form method="get" class="fsearch" role="search">
   <?php foreach (['f' => $f, 'hari' => $hari, 'user' => $uid ?: '', 'dari' => $d1, 'sampai' => $d2] as $k => $v) if ($v !== '') echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">'; ?>
   <button class="sbtn" aria-label="Cari"><i class="fa-solid fa-magnifying-glass"></i></button>
   <input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Cari aktivitas atau nama pengguna...">
  </form>
  <div class="chipbar">
   <span class="flabel">Filter:</span>
   <a class="chip <?= $hari === '1' ? 'on' : '' ?>" href="<?= e($qs(['hari' => $hari === '1' ? null : '1', 'hal' => null])) ?>"><i class="fa-regular fa-calendar-check"></i> Hari Ini</a>
   <?php foreach ($chipMap as $key => [$lbl, $ic, $kw]): ?>
   <a class="chip <?= $f === $key ? 'on' : '' ?>" href="<?= e($qs(['f' => $f === $key ? null : $key, 'hal' => null])) ?>"><i class="fa-solid <?= $ic ?>"></i> <?= e($lbl) ?></a>
   <?php endforeach; ?>
  </div>
 </div>

 <details class="adv" <?= ($uid || $d1 || $d2) ? 'open' : '' ?>>
  <summary>Filter lanjutan: pengguna &amp; rentang tanggal</summary>
  <form class="filter" method="get">
   <?php foreach (['cari' => $cari, 'f' => $f, 'hari' => $hari] as $k => $v) if ($v !== '') echo '<input type="hidden" name="' . $k . '" value="' . e($v) . '">'; ?>
   <label>Pengguna<select name="user"><option value="0">Semua pengguna</option>
    <?php foreach ($users as $u): ?><option value="<?= $u['id_user'] ?>" <?= $uid === (int)$u['id_user'] ? 'selected' : '' ?>><?= e($u['nama_lengkap']) ?></option><?php endforeach; ?></select></label>
   <label>Dari tanggal<input type="date" name="dari" value="<?= e($d1) ?>"></label>
   <label>Sampai tanggal<input type="date" name="sampai" value="<?= e($d2) ?>"></label>
   <div class="fa-act"><button class="btn"><i class="fa-solid fa-magnifying-glass"></i> Terapkan</button><a class="btn ghost" href="log-aktivitas.php">Reset semua</a></div>
  </form>
 </details>

 <div class="card">
  <div class="tw"><table>
   <tr><th>Waktu</th><th>Pengguna</th><th>Aktivitas</th></tr>
   <?php foreach ($rows as $r): [$ik, $kl] = ikon_log($r['aksi']); ?>
   <tr>
    <td class="wk"><b><?= e(waktu_lalu($r['waktu'])) ?></b><small><?= e(date('d M Y, H:i:s', strtotime($r['waktu']))) ?></small></td>
    <td class="who"><b><?= e($r['nama_lengkap'] ?? 'Pengguna terhapus') ?></b>
     <?php if ($r['level']): ?><span class="pill <?= e($r['level']) ?>"><?= e($r['level']) ?></span><?php endif; ?></td>
    <td><div class="aksi"><i class="fa-solid <?= $ik ?> <?= $kl ?>"></i><span><?= e($r['aksi']) ?></span></div></td>
   </tr>
   <?php endforeach; ?>
  </table></div>
  <?php if (!$rows): ?><div class="kosong"><i class="fa-regular fa-folder-open"></i>Tidak ada aktivitas yang cocok dengan filter.</div><?php endif; ?>

  <div class="pager">
   <span>Menampilkan <?= $cnt ? $off + 1 : 0 ?>-<?= min($off + $per, $cnt) ?> dari <?= number_format($cnt, 0, ',', '.') ?> aktivitas</span>
   <nav aria-label="Halaman">
    <?php if ($hal > 1): ?><a href="<?= e($qs(['hal' => $hal - 1])) ?>" aria-label="Sebelumnya"><i class="fa-solid fa-chevron-left"></i></a><?php endif; ?>
    <?php for ($i = max(1, $hal - 2); $i <= min($pages, $hal + 2); $i++): ?>
     <?= $i === $hal ? '<span class="cur">' . $i . '</span>' : '<a href="' . e($qs(['hal' => $i])) . '">' . $i . '</a>' ?>
    <?php endfor; ?>
    <?php if ($hal < $pages): ?><a href="<?= e($qs(['hal' => $hal + 1])) ?>" aria-label="Berikutnya"><i class="fa-solid fa-chevron-right"></i></a><?php endif; ?>
   </nav>
  </div>
 </div>

</main>
</body>
</html>
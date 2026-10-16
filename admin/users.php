<?php
// admin/users.php - Manajemen Pengguna Sistem
// Admin   : CRUD penuh (tambah, ubah, hapus)
// Petugas : hanya melihat (tanpa tombol dan tanpa proses CRUD)
// Peminjam: tidak punya akses
require __DIR__ . '/../koneksi.php';
if (empty($_SESSION['u'])) { go('../index.php'); }

$level_login = strtolower($_SESSION['u']['level'] ?? $_SESSION['u']['role'] ?? '');
if (!in_array($level_login, ['admin', 'petugas'], true)) {
    echo "<script>alert('Akses ditolak! Halaman ini hanya untuk Administrator dan Petugas.'); window.location='../petugas/index.php';</script>";
    exit;
}
$can_edit = ($level_login === 'admin');
$self     = basename($_SERVER['PHP_SELF']);
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }

$levels    = ['Siswa', 'Guru', 'Petugas', 'Admin'];
$pesan     = '';
$aksi_post = '';

// ---------- Proses POST (khusus admin) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$can_edit) {
        $_SESSION['flash_user'] = ['Akses ditolak. Petugas hanya dapat melihat data pengguna.', 'error'];
        go($self);
    }
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_user'] = ['Sesi tidak valid, muat ulang halaman lalu coba lagi.', 'error'];
        go($self);
    }

    $aksi          = $_POST['aksi'] ?? '';
    $aksi_post     = $aksi;
    $nama_lengkap  = trim($_POST['nama_lengkap'] ?? '');
    $username      = trim($_POST['username'] ?? '');
    $password      = trim($_POST['password'] ?? '');
    $level         = ucfirst(strtolower(trim($_POST['level'] ?? 'Siswa')));
    $kelas_jabatan = trim($_POST['kelas_jabatan'] ?? '');
    $id_user       = (int)($_POST['id_user'] ?? 0);
    if (!in_array($level, $levels, true)) { $level = 'Siswa'; }

    if ($aksi === 'hapus') {
        $id_hapus = (int)($_POST['id_user'] ?? 0);
        if ($id_hapus === (int)$_SESSION['u']['id_user']) {
            $_SESSION['flash_user'] = ['Akun yang sedang dipakai tidak bisa dihapus.', 'error'];
        } else {
            $r = q("SELECT username FROM users WHERE id_user = ?", 'i', $id_hapus);
            $d = $r ? $r->fetch_assoc() : null;
            if (!$d) {
                $_SESSION['flash_user'] = ['Pengguna tidak ditemukan.', 'error'];
            } elseif (q("DELETE FROM users WHERE id_user = ?", 'i', $id_hapus)) {
                logact('Menghapus pengguna ' . $d['username']);
                $_SESSION['flash_user'] = ['Akun "' . $d['username'] . '" berhasil dihapus.', 'success'];
            } else {
                $_SESSION['flash_user'] = ['Akun "' . $d['username'] . '" tidak bisa dihapus karena sudah punya riwayat peminjaman.', 'error'];
            }
        }
        go($self);
    }

    if ($aksi === 'tambah') {
        if ($nama_lengkap !== '' && $username !== '' && $password !== '') {
            $cek = q("SELECT id_user FROM users WHERE username = ?", 's', $username);
            if ($cek && $cek->num_rows > 0) {
                $pesan = "Username tersebut sudah digunakan oleh akun lain!";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if (q("INSERT INTO users (nama_lengkap, username, password, level, kelas_jabatan) VALUES (?, ?, ?, ?, ?)", 'sssss', $nama_lengkap, $username, $hash, $level, $kelas_jabatan)) {
                    logact('Menambah pengguna ' . $username);
                    $_SESSION['flash_user'] = ['Akun pengguna baru berhasil ditambahkan!', 'success'];
                    go($self);
                }
                $pesan = "Gagal menyimpan data pengguna.";
            }
        } else {
            $pesan = "Lengkapi nama, username, dan password.";
        }
    } elseif ($aksi === 'edit' && $id_user > 0) {
        if ($nama_lengkap !== '' && $username !== '') {
            $cek = q("SELECT id_user FROM users WHERE username = ? AND id_user <> ?", 'si', $username, $id_user);
            if ($cek && $cek->num_rows > 0) {
                $pesan = "Username tersebut sudah digunakan oleh akun lain!";
            } else {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ok = q("UPDATE users SET nama_lengkap = ?, username = ?, password = ?, level = ?, kelas_jabatan = ? WHERE id_user = ?", 'sssssi', $nama_lengkap, $username, $hash, $level, $kelas_jabatan, $id_user);
                } else {
                    $ok = q("UPDATE users SET nama_lengkap = ?, username = ?, level = ?, kelas_jabatan = ? WHERE id_user = ?", 'ssssi', $nama_lengkap, $username, $level, $kelas_jabatan, $id_user);
                }
                if ($ok) {
                    logact('Mengubah pengguna ' . $username);
                    $_SESSION['flash_user'] = ['Data pengguna berhasil diperbarui!', 'success'];
                    go($self);
                }
                $pesan = "Gagal memperbarui data pengguna.";
            }
        } else {
            $pesan = "Nama lengkap dan username wajib diisi.";
        }
    }
}

$flash_user = $_SESSION['flash_user'] ?? null;
unset($_SESSION['flash_user']);

// ---------- Pencarian & Filter Role ----------
$cari  = trim($_GET['cari'] ?? '');
$peran = trim($_GET['role'] ?? '');

$sql = "SELECT * FROM users WHERE 1=1";
$params = [];
$types  = '';

if ($cari !== '') {
    $sql .= " AND (nama_lengkap LIKE ? OR username LIKE ?)";
    $params[] = "%$cari%";
    $params[] = "%$cari%";
    $types  .= 'ss';
}
if ($peran !== '') {
    $sql .= " AND level = ?";
    $params[] = $peran;
    $types  .= 's';
}
$sql .= " ORDER BY level ASC, nama_lengkap ASC LIMIT 300";

$q_users = !empty($params) ? q($sql, $types, ...$params) : q($sql);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manajemen User - Sarpras SMK Negeri 3 Kendal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root{
 --ink:#0D2B2E; --teal:#0F5F58; --teal-deep:#0A3A36; --sun:#FFC54D; --sun-deep:#B47A00; --sun-soft:#FFE3A1;
 --cream:#E3EDE9; --ivory:#F6FAF8; --line:#CDDFD8; --muted:#5F7A78;
 --serif:'Cormorant Garamond',Georgia,serif; --sans:'Plus Jakarta Sans',system-ui,sans-serif;
 --grad-hidup:linear-gradient(135deg,#0A4A46 0%,#0F6B61 55%,#177F72 120%);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);color:var(--ink);min-height:100vh;display:flex;overflow:hidden;
 background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%230F5F58' stroke-opacity='.07' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%230F5F58' fill-opacity='.12'/%3E%3C/svg%3E") 0 0/56px 56px,var(--cream)}

.main-content{margin-left:280px;flex:1;height:100vh;overflow-y:auto;padding:30px 34px 40px;scrollbar-width:thin;scrollbar-color:var(--teal) transparent}
.main-content::-webkit-scrollbar{width:8px}
.main-content::-webkit-scrollbar-thumb{background:linear-gradient(var(--teal),#17A38D);border-radius:8px}

.top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;background:var(--ivory);padding:20px 24px;border-radius:20px;border:1px solid var(--line);box-shadow:0 10px 25px -15px rgba(15,95,88,.2)}
.top-bar h1{font-family:var(--serif);font-size:26px;font-weight:700}
.mode-tag{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:999px;font-size:11.5px;font-weight:700;background:rgba(255,152,0,.18);color:#b36b00}

.content-grid{display:grid;grid-template-columns:340px 1fr;gap:24px}
.content-grid.single{grid-template-columns:1fr}
.card{background:var(--ivory);border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 12px 25px -15px rgba(15,95,88,.15)}
.card h3{font-family:var(--serif);font-size:20px;font-weight:700;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)}

.form-group{margin-bottom:14px}
.form-group label{display:block;font-size:11.5px;font-weight:700;color:var(--teal);margin-bottom:4px;text-transform:uppercase}
.form-control{width:100%;padding:10px 14px;border-radius:10px;border:1px solid var(--line);font-size:13px;background:#fff;outline:none}
.form-control:focus{border-color:var(--teal)}

.btn-submit{width:100%;padding:10px;border-radius:10px;background:var(--grad-hidup);color:#fff;border:0;font-weight:700;font-size:13px;cursor:pointer;box-shadow:0 4px 12px rgba(15,125,112,.3)}

.filter-bar{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;align-items:center}
.filter-bar input{flex:1;padding:8px 12px;border-radius:8px;border:1px solid var(--line);font-size:12.5px;background:#fff;outline:none}
.btn-filter{padding:8px 14px;border-radius:8px;background:var(--teal);color:#fff;border:0;font-weight:700;font-size:12px;cursor:pointer}
.btn-shortcut{padding:6px 10px;border-radius:8px;background:var(--cream);color:var(--teal);border:1px solid var(--line);font-weight:700;font-size:11px;text-decoration:none}
.btn-shortcut:hover, .btn-shortcut.active{background:var(--teal);color:#fff}

table{width:100%;border-collapse:collapse;font-size:12.5px}
th, td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--line);vertical-align:middle}
th{font-weight:700;color:var(--teal);text-transform:uppercase;font-size:10.5px;background:rgba(15,95,88,.05)}

.badge{display:inline-block;padding:4px 9px;border-radius:999px;font-size:9.5px;font-weight:700;text-transform:uppercase}
.badge.admin{background:rgba(220,53,69,.2);color:#dc3545}
.badge.petugas{background:rgba(255,152,0,.2);color:#b36b00}
.badge.guru{background:rgba(13,110,253,.2);color:#0d6efd}
.badge.siswa,.badge.peminjam{background:rgba(25,135,84,.2);color:#198754}

.aksi-wrap{display:flex;gap:6px;align-items:center}
.aksi-wrap form{margin:0}
.btn-action{padding:5px 10px;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none;display:inline-flex;align-items:center;gap:4px;cursor:pointer;border:0;font-family:inherit}
.btn-edit{background:rgba(255,193,7,.2);color:#b38600}
.btn-hapus{background:rgba(220,53,69,.2);color:#dc3545}

.modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,42,39,0.6);backdrop-filter:blur(4px);z-index:1000;place-items:center;padding:20px}
.modal-overlay.active{display:grid}
.modal-card{background:var(--ivory);border:1px solid var(--line);border-radius:24px;width:100%;max-width:440px;max-height:calc(100vh - 40px);overflow-y:auto;padding:28px;box-shadow:0 20px 40px rgba(0,0,0,.3)}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--line)}
.modal-header h3{font-family:var(--serif);font-size:20px;font-weight:700}
.modal-close{background:0;border:0;font-size:18px;cursor:pointer;color:var(--muted)}

.alert{padding:12px;border-radius:10px;margin-bottom:16px;font-size:12.5px;font-weight:600}
.alert.success{background:rgba(25,135,84,.15);color:#198754}
.alert.error{background:rgba(220,53,69,.15);color:#dc3545}
.alert.info{background:rgba(13,110,253,.12);color:#0b5ed7}
.footer{margin-top:30px;text-align:center;font-size:11px;color:var(--muted)}
@media(max-width:1024px){.content-grid{grid-template-columns:1fr}.main-content{margin-left:72px;padding:16px}}
</style>
<?php if ($can_edit): ?>
<script>
function bukaModalEditUser(btn) {
    var d = JSON.parse(btn.getAttribute('data-u'));
    document.getElementById('edit_id_user').value = d.id;
    document.getElementById('edit_nama_lengkap').value = d.nama;
    document.getElementById('edit_username').value = d.username;
    document.getElementById('edit_level').value = d.level;
    document.getElementById('edit_kelas_jabatan').value = d.kelas;
    document.getElementById('modalEditUser').classList.add('active');
}
function tutupModalEditUser() {
    document.getElementById('modalEditUser').classList.remove('active');
}
</script>
<?php endif; ?>
</head>
<body>
<!-- Memuat header dari folder petugas -->
<?php include __DIR__ . '/../petugas/header.php'; ?>
<main class="main-content">
 <div class="top-bar">
  <div>
   <h1><?= $can_edit ? 'Manajemen Pengguna Sistem' : 'Data Pengguna Sistem' ?></h1>
   <p style="font-size:12.5px;color:var(--muted)">Data akun Administrator, Petugas, Guru, dan Siswa.</p>
  </div>
  <?php if (!$can_edit): ?><span class="mode-tag"><i class="fa-solid fa-eye"></i> Mode lihat saja</span><?php endif; ?>
 </div>

 <?php if ($flash_user): ?>
  <div class="alert <?= e($flash_user[1]) ?>" role="alert"><?= e($flash_user[0]) ?></div>
 <?php endif; ?>
 <?php if (!$can_edit): ?>
  <div class="alert info"><i class="fa-solid fa-circle-info"></i> Petugas hanya dapat melihat data pengguna. Tambah, ubah, dan hapus akun hanya bisa dilakukan oleh Administrator.</div>
 <?php endif; ?>

 <div class="content-grid <?= $can_edit ? '' : 'single' ?>">
  <?php if ($can_edit): ?>
  <!-- Form Tambah (khusus admin) -->
  <div class="card">
   <h3>Tambah Pengguna Baru</h3>
   <?php if ($pesan !== '' && $aksi_post === 'tambah'): ?>
    <div class="alert error"><?= e($pesan) ?></div>
   <?php endif; ?>

   <form method="POST">
    <input type="hidden" name="aksi" value="tambah">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <div class="form-group">
     <label>Nama Lengkap</label>
     <input type="text" name="nama_lengkap" class="form-control" placeholder="Nama lengkap..." required>
    </div>
    <div class="form-group">
     <label>Username / NIS / NIP</label>
     <input type="text" name="username" class="form-control" placeholder="Username login..." required>
    </div>
    <div class="form-group">
     <label>Password</label>
     <input type="password" name="password" class="form-control" placeholder="Kata sandi..." required>
    </div>
    <div class="form-group">
     <label>Level / Peran</label>
     <select name="level" class="form-control">
      <?php foreach ($levels as $lv): ?><option value="<?= $lv ?>"><?= $lv ?></option><?php endforeach; ?>
     </select>
    </div>
    <div class="form-group">
     <label>Kelas / Jabatan</label>
     <input type="text" name="kelas_jabatan" class="form-control" placeholder="Contoh: XII RPL 1 / Guru Mapel">
    </div>
    <button type="submit" class="btn-submit"><i class="fa-solid fa-user-plus"></i> Tambah Akun</button>
   </form>
  </div>
  <?php endif; ?>

  <!-- Tabel & Filter User -->
  <div class="card">
   <h3>Daftar Pengguna Terdaftar</h3>
   <?php if ($pesan !== '' && $aksi_post === 'edit'): ?>
    <div class="alert error"><?= e($pesan) ?></div>
   <?php endif; ?>

   <form method="GET" class="filter-bar">
    <input type="text" name="cari" placeholder="Cari nama atau username..." value="<?= e($cari) ?>">
    <?php if ($peran !== ''): ?><input type="hidden" name="role" value="<?= e($peran) ?>"><?php endif; ?>
    <button type="submit" class="btn-filter"><i class="fa-solid fa-magnifying-glass"></i></button>
    <a href="<?= e($self) ?>" class="btn-shortcut <?= $peran === '' ? 'active' : '' ?>">Semua</a>
    <?php foreach (['Admin', 'Petugas', 'Guru', 'Siswa'] as $lv): ?>
     <a href="<?= e($self) ?>?role=<?= $lv ?>" class="btn-shortcut <?= strcasecmp($peran, $lv) === 0 ? 'active' : '' ?>"><?= $lv ?></a>
    <?php endforeach; ?>
   </form>

   <table>
    <thead>
     <tr>
      <th style="width:<?= $can_edit ? '25' : '30' ?>%">Nama Lengkap</th>
      <th style="width:<?= $can_edit ? '18' : '22' ?>%">Username</th>
      <th style="width:<?= $can_edit ? '15' : '18' ?>%">Level</th>
      <th style="width:<?= $can_edit ? '22' : '30' ?>%">Kelas / Jabatan</th>
      <?php if ($can_edit): ?><th style="width:20%">Aksi</th><?php endif; ?>
     </tr>
    </thead>
    <tbody>
     <?php if ($q_users && $q_users->num_rows > 0): ?>
         <?php while ($u = $q_users->fetch_assoc()):
             $lvl = strtolower($u['level']);
             $data_edit = json_encode([
                 'id' => (int)$u['id_user'], 'nama' => $u['nama_lengkap'], 'username' => $u['username'],
                 'level' => ucfirst($lvl), 'kelas' => $u['kelas_jabatan'] ?? '',
             ]);
         ?>
         <tr>
          <td><b><?= e($u['nama_lengkap']) ?></b></td>
          <td><?= e($u['username']) ?></td>
          <td><span class="badge <?= e($lvl) ?>"><?= e($u['level']) ?></span></td>
          <td><?= e($u['kelas_jabatan'] ?? '-') ?></td>
          <?php if ($can_edit): ?>
          <td>
           <div class="aksi-wrap">
            <button type="button" class="btn-action btn-edit" title="Edit" data-u="<?= e($data_edit) ?>" onclick="bukaModalEditUser(this)"><i class="fa-solid fa-pen-to-square"></i></button>
            <?php if ((int)$u['id_user'] !== (int)$_SESSION['u']['id_user']): ?>
             <form method="POST" onsubmit="return confirm('Hapus akun pengguna ini?')">
              <input type="hidden" name="aksi" value="hapus">
              <input type="hidden" name="id_user" value="<?= (int)$u['id_user'] ?>">
              <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
              <button type="submit" class="btn-action btn-hapus" title="Hapus"><i class="fa-solid fa-trash"></i></button>
             </form>
            <?php endif; ?>
           </div>
          </td>
          <?php endif; ?>
         </tr>
         <?php endwhile; ?>
     <?php else: ?>
         <tr>
          <td colspan="<?= $can_edit ? 5 : 4 ?>" style="text-align:center;padding:30px;color:var(--muted)">Belum ada data pengguna yang terdaftar.</td>
         </tr>
     <?php endif; ?>
    </tbody>
   </table>
  </div>
 </div>

 <?php if ($can_edit): ?>
 <!-- Modal Edit User (khusus admin) -->
 <div class="modal-overlay" id="modalEditUser">
  <div class="modal-card">
   <div class="modal-header">
    <h3>Edit Data Pengguna</h3>
    <button type="button" class="modal-close" onclick="tutupModalEditUser()" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>
   </div>
   <form method="POST">
    <input type="hidden" name="aksi" value="edit">
    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
    <input type="hidden" name="id_user" id="edit_id_user">

    <div class="form-group">
     <label>Nama Lengkap</label>
     <input type="text" name="nama_lengkap" id="edit_nama_lengkap" class="form-control" required>
    </div>
    <div class="form-group">
     <label>Username / NIS / NIP</label>
     <input type="text" name="username" id="edit_username" class="form-control" required>
    </div>
    <div class="form-group">
     <label>Password Baru (Opsional)</label>
     <input type="password" name="password" class="form-control" placeholder="Kosongkan jika tidak diubah">
    </div>
    <div class="form-group">
     <label>Level / Peran</label>
     <select name="level" id="edit_level" class="form-control">
      <?php foreach ($levels as $lv): ?><option value="<?= $lv ?>"><?= $lv ?></option><?php endforeach; ?>
     </select>
    </div>
    <div class="form-group">
     <label>Kelas / Jabatan</label>
     <input type="text" name="kelas_jabatan" id="edit_kelas_jabatan" class="form-control">
    </div>
    <button type="submit" class="btn-submit" style="margin-top:8px"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
   </form>
  </div>
 </div>
 <?php endif; ?>

 <div class="footer"><?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal</div>
</main>
</body>
</html>
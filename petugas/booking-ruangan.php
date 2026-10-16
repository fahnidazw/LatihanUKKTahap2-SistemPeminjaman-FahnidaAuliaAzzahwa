
<?php
// petugas/booking-ruangan.php
// Kalender Matriks Booking Ruangan & Laboratorium

require __DIR__ . '/../koneksi.php';

if (empty($_SESSION['u'])) {
    go('../index.php');
}

$kategori_filter = trim($_GET['kategori'] ?? 'Teori');
$tanggal_filter = trim($_GET['tanggal'] ?? date('Y-m-d'));
$jurusan_filter = strtoupper(trim($_GET['jurusan'] ?? ''));

// Daftar jurusan laboratorium
$daftar_jurusan = [
    'RPL' => 'Rekayasa Perangkat Lunak',
    'TKI' => 'Teknik Komputer dan Informatika',
    'TEI' => 'Teknik Elektronika Industri',
    'TKJ' => 'Teknik Komputer dan Jaringan',
    'TO'  => 'Teknik Otomotif'
];

// Validasi kategori
$kategori_diizinkan = [
    'Teori',
    'Laboratorium',
    'Fasilitas Umum'
];

if (!in_array($kategori_filter, $kategori_diizinkan, true)) {
    $kategori_filter = 'Teori';
}

// Validasi jurusan
if (!array_key_exists($jurusan_filter, $daftar_jurusan)) {
    $jurusan_filter = '';
}

// Validasi tanggal
$tanggal_obj = DateTime::createFromFormat('!Y-m-d', $tanggal_filter);

if (
    !$tanggal_obj ||
    $tanggal_obj->format('Y-m-d') !== $tanggal_filter
) {
    $tanggal_filter = date('Y-m-d');
}

// Query berdasarkan kategori
if ($kategori_filter === 'Laboratorium') {

    $sql = "
        SELECT *
        FROM ruangan
        WHERE (
            kategori_ruang LIKE '%Laboratorium%'
            OR kategori_ruang LIKE '%Lab%'
            OR kategori_ruang LIKE '%Bengkel%'
        )
    ";

    // Jurusan berasal dari daftar kode yang sudah divalidasi.
    // Karena nilainya dibatasi pada daftar di atas, nilai aman
    // untuk dimasukkan sebagai literal SQL.
    if ($jurusan_filter !== '') {
        $sql .= " AND UPPER(TRIM(jurusan)) = '" .
            $jurusan_filter . "'";
    }

    $sql .= " ORDER BY nama_ruangan ASC";

} elseif ($kategori_filter === 'Fasilitas Umum') {

    $sql = "
        SELECT *
        FROM ruangan
        WHERE kategori_ruang LIKE '%Fasilitas%'
           OR kategori_ruang LIKE '%Umum%'
        ORDER BY nama_ruangan ASC
    ";

} else {

    $sql = "
        SELECT *
        FROM ruangan
        WHERE kategori_ruang LIKE '%Teori%'
           OR kategori_ruang LIKE '%Kelas%'
        ORDER BY nama_ruangan ASC
    ";
}

$q_ruangan = q($sql);

// Escape HTML
function br_e($value)
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Kalender Booking Ruangan - Sarpras Sekolah</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --ink: #0D2B2E;
            --teal: #0F5F58;
            --teal-deep: #0A3A36;
            --cream: #E3EDE9;
            --ivory: #F6FAF8;
            --line: #CDDFD8;
            --muted: #5F7A78;
            --serif: 'Cormorant Garamond', Georgia, serif;
            --sans: 'Plus Jakarta Sans', system-ui, sans-serif;
            --grad-hidup: linear-gradient(135deg, #0A4A46, #0F6B61 55%, #177F72);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--sans);
            color: var(--ink);
            min-height: 100vh;
            display: flex;
            overflow: hidden;
            background:
                url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='56' height='56' viewBox='0 0 56 56'%3E%3Cg fill='none' stroke='%230F5F58' stroke-opacity='.07' stroke-width='1'%3E%3Cpath d='M28 3l25 25-25 25L3 28z'/%3E%3Cpath d='M28 15l13 13-13 13-13-13z'/%3E%3C/g%3E%3Ccircle cx='28' cy='28' r='1.7' fill='%230F5F58' fill-opacity='.12'/%3E%3C/svg%3E") 0 0 / 56px 56px,
                var(--cream);
        }

        .main-content {
            margin-left: 280px;
            flex: 1;
            min-width: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 30px 34px 40px;
            scrollbar-width: thin;
            scrollbar-color: var(--teal) transparent;
        }

        .main-content::-webkit-scrollbar {
            width: 8px;
        }

        .main-content::-webkit-scrollbar-thumb {
            background: linear-gradient(var(--teal), #17A38D);
            border-radius: 8px;
        }

        .top-bar {
            margin-bottom: 20px;
            background: var(--ivory);
            padding: 22px 24px;
            border-radius: 20px;
            border: 1px solid var(--line);
            box-shadow: 0 10px 25px -15px rgba(15, 95, 88, .2);
        }

        .top-bar h1 {
            font-family: var(--serif);
            font-size: 28px;
            font-weight: 700;
        }

        .top-bar p {
            margin-top: 5px;
            color: var(--muted);
            font-size: 12.5px;
            line-height: 1.7;
        }

        .tab-container,
        .jurusan-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 18px;
        }

        .tab-btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            padding: 11px 17px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 12px;
            text-decoration: none;
            border: 1px solid var(--line);
            background: var(--ivory);
            color: var(--muted);
            transition: .2s;
        }

        .tab-btn:hover {
            border-color: var(--teal);
            color: var(--teal);
        }

        .tab-btn.active {
            background: var(--grad-hidup);
            color: white;
            border-color: transparent;
            box-shadow: 0 6px 15px rgba(15, 125, 112, .25);
        }

        .subkategori-panel {
            background: var(--ivory);
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 18px;
            margin-bottom: 20px;
        }

        .subkategori-heading {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .subkategori-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            border-radius: 13px;
            color: white;
            background: var(--grad-hidup);
        }

        .subkategori-heading h2 {
            font-family: var(--serif);
            font-size: 22px;
        }

        .subkategori-heading p {
            font-size: 11px;
            color: var(--muted);
            margin-top: 3px;
        }

        .jurusan-btn {
            flex: 1;
            min-width: 100px;
            padding: 13px 12px;
            border: 1px solid var(--line);
            border-radius: 13px;
            background: white;
            color: var(--ink);
            text-decoration: none;
            transition: .2s;
        }

        .jurusan-btn:hover {
            border-color: var(--teal);
            background: #EDF6F2;
        }

        .jurusan-btn.active {
            background: var(--teal);
            color: white;
            border-color: var(--teal);
        }

        .jurusan-btn strong {
            display: block;
            font-size: 16px;
            margin-bottom: 4px;
        }

        .jurusan-btn span {
            display: block;
            font-size: 10px;
            line-height: 1.5;
        }

        .filter-date {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            margin-bottom: 24px;
            background: var(--ivory);
            padding: 15px 20px;
            border-radius: 16px;
            border: 1px solid var(--line);
        }

        .filter-date label {
            font-size: 12px;
            font-weight: 700;
            color: var(--teal);
        }

        .filter-date input {
            padding: 9px 12px;
            border-radius: 10px;
            border: 1px solid var(--line);
            font-family: var(--sans);
            font-size: 12px;
            background: white;
            outline: none;
        }

        .legend {
            margin-left: auto;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            font-size: 10.5px;
        }

        .calendar-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .calendar-title h2 {
            font-family: var(--serif);
            font-size: 24px;
        }

        .calendar-title span {
            font-size: 11px;
            color: var(--muted);
        }

        .calendar-matrix {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(145px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .matrix-box {
            border-radius: 18px;
            padding: 18px 12px;
            text-align: center;
            cursor: pointer;
            transition: .25s;
            border: 1px solid rgba(0, 0, 0, .08);
            box-shadow: 0 8px 20px -10px rgba(0, 0, 0, .15);
        }

        .matrix-box:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px -8px rgba(0, 0, 0, .2);
        }

        .matrix-box.tersedia {
            background: rgba(25, 135, 84, .12);
            border-color: rgba(25, 135, 84, .3);
        }

        .matrix-box.terpakai {
            background: rgba(220, 53, 69, .12);
            border-color: rgba(220, 53, 69, .3);
        }

        .matrix-box.booking {
            background: rgba(255, 152, 0, .14);
            border-color: rgba(255, 152, 0, .35);
        }

        .matrix-box .kode {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .matrix-box .no {
            font-family: var(--serif);
            font-size: 30px;
            font-weight: 700;
            margin: 4px 0;
        }

        .matrix-box .nama {
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .matrix-status-badge {
            display: inline-block;
            margin-top: 9px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .tersedia .matrix-status-badge {
            background: rgba(25, 135, 112, .2);
            color: #198754;
        }

        .terpakai .matrix-status-badge {
            background: rgba(220, 53, 69, .2);
            color: #dc3545;
        }

        .booking .matrix-status-badge {
            background: rgba(255, 152, 0, .25);
            color: #9B6100;
        }

        .empty-state {
            grid-column: 1 / -1;
            text-align: center;
            padding: 50px 20px;
            color: var(--muted);
            background: var(--ivory);
            border: 1px dashed var(--line);
            border-radius: 18px;
        }

        .empty-state i {
            font-size: 32px;
            display: block;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            font-family: var(--serif);
            font-size: 23px;
            color: var(--ink);
            margin-bottom: 6px;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10, 42, 39, .6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            place-items: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: grid;
        }

        .modal-card {
            background: var(--ivory);
            border: 1px solid var(--line);
            border-radius: 24px;
            width: 100%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, .3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--line);
        }

        .modal-header h3 {
            font-family: var(--serif);
            font-size: 23px;
        }

        .modal-close {
            border: 0;
            background: transparent;
            font-size: 19px;
            cursor: pointer;
            color: var(--muted);
        }

        .modal-body {
            font-size: 13px;
            line-height: 1.8;
            overflow-wrap: anywhere;
        }

        .modal-body p {
            margin-bottom: 10px;
        }

        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 11px;
            color: var(--muted);
        }

        @media (max-width: 1000px) {
            .main-content {
                margin-left: 72px;
                padding: 22px;
            }

            .legend {
                width: 100%;
                margin-left: 0;
            }
        }

        @media (max-width: 600px) {
            .main-content {
                padding: 14px;
            }

            .top-bar {
                padding: 18px;
            }

            .top-bar h1 {
                font-size: 24px;
            }

            .jurusan-btn {
                min-width: 75px;
            }

            .calendar-matrix {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .matrix-box {
                padding: 14px 7px;
            }

            .filter-date {
                padding: 14px;
            }

            .modal-card {
                padding: 22px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . '/header.php'; ?>

<main class="main-content">

    <div class="top-bar">
        <h1>Kalender Matriks Booking Ruangan</h1>
        <p>
            Pantau ruangan sekolah berdasarkan kategori, jurusan,
            dan tanggal penggunaan.
        </p>
    </div>

    <!-- KATEGORI UTAMA -->
    <div class="tab-container">

        <a
            href="booking-ruangan.php?kategori=Teori"
            class="tab-btn <?= $kategori_filter === 'Teori' ? 'active' : '' ?>"
        >
            <i class="fa-solid fa-school"></i>
            Ruang Teori
        </a>

        <a
            href="booking-ruangan.php?kategori=Laboratorium"
            class="tab-btn <?= $kategori_filter === 'Laboratorium' ? 'active' : '' ?>"
        >
            <i class="fa-solid fa-laptop-code"></i>
            Laboratorium
        </a>

        <a
            href="booking-ruangan.php?kategori=Fasilitas%20Umum"
            class="tab-btn <?= $kategori_filter === 'Fasilitas Umum' ? 'active' : '' ?>"
        >
            <i class="fa-solid fa-building-columns"></i>
            Fasilitas Umum
        </a>

    </div>

    <!-- SUBKATEGORI JURUSAN -->
    <?php if ($kategori_filter === 'Laboratorium'): ?>

        <section class="subkategori-panel">

            <div class="subkategori-heading">
                <div class="subkategori-icon">
                    <i class="fa-solid fa-flask"></i>
                </div>

                <div>
                    <h2>Laboratorium Jurusan</h2>
                    <p>Pilih jurusan untuk melihat laboratorium terkait.</p>
                </div>
            </div>

            <div class="jurusan-container">

                <a
                    href="booking-ruangan.php?kategori=Laboratorium"
                    class="jurusan-btn <?= $jurusan_filter === '' ? 'active' : '' ?>"
                >
                    <strong>Semua</strong>
                    <span>Semua laboratorium</span>
                </a>

                <?php foreach ($daftar_jurusan as $kode => $nama): ?>

                    <a
                        href="booking-ruangan.php?kategori=Laboratorium&amp;jurusan=<?= urlencode($kode) ?>"
                        class="jurusan-btn <?= $jurusan_filter === $kode ? 'active' : '' ?>"
                    >
                        <strong><?= br_e($kode) ?></strong>
                        <span><?= br_e($nama) ?></span>
                    </a>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

    <!-- FILTER TANGGAL -->
    <form method="GET" class="filter-date">

        <input
            type="hidden"
            name="kategori"
            value="<?= br_e($kategori_filter) ?>"
        >

        <?php if ($kategori_filter === 'Laboratorium' && $jurusan_filter !== ''): ?>
            <input
                type="hidden"
                name="jurusan"
                value="<?= br_e($jurusan_filter) ?>"
            >
        <?php endif; ?>

        <label for="tanggal">
            <i class="fa-regular fa-calendar"></i>
            Tanggal Kalender
        </label>

        <input
            type="date"
            id="tanggal"
            name="tanggal"
            value="<?= br_e($tanggal_filter) ?>"
            onchange="this.form.submit()"
        >

        <div class="legend">
            <span><i class="fa-solid fa-square" style="color:#198754"></i> Tersedia</span>
            <span><i class="fa-solid fa-square" style="color:#dc3545"></i> Dipakai</span>
            <span><i class="fa-solid fa-square" style="color:#e99a18"></i> Booking</span>
        </div>

    </form>

    <!-- JUDUL KALENDER -->
    <div class="calendar-title">
        <h2>
            <?php
            if ($kategori_filter === 'Laboratorium') {
                echo $jurusan_filter !== ''
                    ? 'Laboratorium ' . br_e($jurusan_filter)
                    : 'Semua Laboratorium';
            } elseif ($kategori_filter === 'Fasilitas Umum') {
                echo 'Fasilitas Umum';
            } else {
                echo 'Ruang Teori';
            }
            ?>
        </h2>

        <span><?= br_e(date('d-m-Y', strtotime($tanggal_filter))) ?></span>
    </div>

    <!-- KALENDER MATRIKS -->
    <div class="calendar-matrix">

        <?php if (
            $q_ruangan instanceof mysqli_result &&
            $q_ruangan->num_rows > 0
        ): ?>

            <?php while ($r = $q_ruangan->fetch_assoc()): ?>

                <?php
                // Status simulasi, belum mengambil transaksi booking aktual.
                $statuses = ['tersedia', 'terpakai', 'booking'];

                $status_index = crc32(
                    ($r['kode_ruangan'] ?? '') . $tanggal_filter
                ) % count($statuses);

                $status_kode = $statuses[$status_index];

                if ($status_kode === 'terpakai') {
                    $jam_sesi = '07:30 - 15:00 WIB (KBM Reguler)';
                    $ket_status = 'Ditandai sedang digunakan.';
                    $label_status = 'Dipakai';
                } elseif ($status_kode === 'booking') {
                    $jam_sesi = '13:00 - 15:00 WIB (Praktikum Tambahan)';
                    $ket_status = 'Ditandai sudah dibooking.';
                    $label_status = 'Booking';
                } else {
                    $jam_sesi = 'Sepanjang Hari';
                    $ket_status = 'Ditandai tersedia.';
                    $label_status = 'Tersedia';
                }

                $nomor_ruangan = preg_replace(
                    '/[^0-9]/',
                    '',
                    $r['nama_ruangan'] ?? ''
                );

                if ($nomor_ruangan === '') {
                    $nomor_ruangan = '01';
                }
                ?>

                <div
                    class="matrix-box <?= br_e($status_kode) ?>"
                    role="button"
                    tabindex="0"
                    data-nama="<?= br_e($r['nama_ruangan'] ?? '-') ?>"
                    data-kode="<?= br_e($r['kode_ruangan'] ?? '-') ?>"
                    data-status="<?= br_e($status_kode) ?>"
                    data-keterangan="<?= br_e($ket_status) ?>"
                    data-jurusan="<?= br_e($r['jurusan'] ?? '-') ?>"
                    data-kapasitas="<?= (int) ($r['kapasitas'] ?? 0) ?>"
                    data-id="<?= (int) ($r['id_ruangan'] ?? 0) ?>"
                    data-jamsesi="<?= br_e($jam_sesi) ?>"
                    onclick="bukaModalDariElemen(this)"
                    onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();bukaModalDariElemen(this)}"
                >

                    <div class="kode">
                        <?= br_e($r['kode_ruangan'] ?? '-') ?>
                    </div>

                    <div class="no"><?= br_e($nomor_ruangan) ?></div>

                    <div class="nama">
                        <?= br_e($r['nama_ruangan'] ?? '-') ?>
                    </div>

                    <span class="matrix-status-badge">
                        <?= br_e($label_status) ?>
                    </span>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty-state">
                <i class="fa-regular fa-building"></i>
                <h3>Ruangan Belum Ditemukan</h3>
                <p>
                    <?php if (
                        $kategori_filter === 'Laboratorium' &&
                        $jurusan_filter !== ''
                    ): ?>
                        Belum ada laboratorium dengan jurusan
                        <?= br_e($jurusan_filter) ?>.
                        Periksa nilai kolom jurusan pada database.
                    <?php else: ?>
                        Tidak ada data ruangan untuk kategori ini.
                    <?php endif; ?>
                </p>
            </div>

        <?php endif; ?>

    </div>

    <!-- MODAL DETAIL -->
    <div
        class="modal-overlay"
        id="modalDetail"
        onclick="tutupModalLuar(event)"
    >
        <div
            class="modal-card"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modalTitle"
        >

            <div class="modal-header">
                <h3 id="modalTitle">Detail Ruangan</h3>

                <button
                    type="button"
                    class="modal-close"
                    onclick="tutupModal()"
                    aria-label="Tutup modal"
                >
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body" id="modalBody"></div>

            <div class="modal-footer">
                <a href="#" id="btnAjukanBooking" class="tab-btn active">
                    <i class="fa-solid fa-calendar-check"></i>
                    Pesan Ruangan Ini
                </a>
            </div>

        </div>
    </div>

    <div class="footer">
        <?= date('Y') ?> &bull; Sarpras SMK Negeri 3 Kendal
    </div>

</main>

<script>
function bukaModalDariElemen(el) {
    const nama = el.dataset.nama || '-';
    const kode = el.dataset.kode || '-';
    const status = el.dataset.status || 'tersedia';
    const keterangan = el.dataset.keterangan || '-';
    const jurusan = el.dataset.jurusan || '-';
    const kapasitas = el.dataset.kapasitas || '0';
    const idRuang = el.dataset.id || '';
    const jamSesi = el.dataset.jamsesi || '-';

    document.getElementById('modalTitle').textContent =
        nama + ' (' + kode + ')';

    const modalBody = document.getElementById('modalBody');
    modalBody.replaceChildren();

    let badgeColor = '#198754';
    let statusLabel = 'Tersedia';

    if (status === 'terpakai') {
        badgeColor = '#dc3545';
        statusLabel = 'Dipakai';
    } else if (status === 'booking') {
        badgeColor = '#b36b00';
        statusLabel = 'Booking';
    }

    function tambahBaris(label, nilai, warna = '') {
        const p = document.createElement('p');
        const strong = document.createElement('strong');

        strong.textContent = label + ': ';
        p.appendChild(strong);

        if (warna) {
            const span = document.createElement('span');
            span.textContent = nilai;
            span.style.color = warna;
            span.style.fontWeight = '700';
            p.appendChild(span);
        } else {
            p.appendChild(document.createTextNode(nilai));
        }

        modalBody.appendChild(p);
    }

    tambahBaris('Kode Ruangan', kode);
    tambahBaris('Jurusan', jurusan);
    tambahBaris('Kapasitas Maksimal', kapasitas + ' siswa');
    tambahBaris('Status Ruangan', statusLabel, badgeColor);
    tambahBaris('Waktu / Sesi', jamSesi);
    tambahBaris('Keterangan', keterangan);

    const btnBooking = document.getElementById('btnAjukanBooking');

    if (status === 'terpakai') {
        btnBooking.style.display = 'none';

        const peringatan = document.createElement('div');
        peringatan.style.cssText =
            'margin-top:14px;padding:12px;' +
            'background:rgba(220,53,69,.1);color:#dc3545;' +
            'border-radius:10px;font-size:12px;font-weight:700;';

        peringatan.textContent =
            'Ruangan ditandai sedang dipakai sehingga tombol pengajuan disembunyikan.';

        modalBody.appendChild(peringatan);
    } else {
        btnBooking.style.display = 'inline-flex';
        btnBooking.href =
            'ajukan.php?ruangan=' + encodeURIComponent(idRuang);
    }

    document.getElementById('modalDetail').classList.add('active');
}

function tutupModal() {
    document.getElementById('modalDetail').classList.remove('active');
}

function tutupModalLuar(event) {
    if (event.target.id === 'modalDetail') {
        tutupModal();
    }
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        tutupModal();
    }
});
</script>

</body>
</html>

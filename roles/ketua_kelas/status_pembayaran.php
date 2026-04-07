<?php
session_start();
include '../../config/connect.php';

// Redirect jika belum login
if (!isset($_SESSION['login'])) {
    header('Location: ../../index.php');
    exit;
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

date_default_timezone_set('Asia/Jakarta');

// Tanggal saat ini untuk kartu ringkasan
$tahunSekarang = date('Y');
$bulanSekarang = date('n');
// Hitung minggu sekarang, maksimal 4
$mingguSekarang = min(4, ceil(date('j') / 7));

// Ambil nilai filter dari URL
$tahun = $_GET['tahun'] ?? null;
$bulan = $_GET['bulan'] ?? null;
$minggu = $_GET['minggu'] ?? null;
$reset = $_GET['reset'] ?? null;
$sort = $_GET['sort'] ?? null;

// Validasi nilai sort agar hanya menerima nilai yang diizinkan
if (!in_array($sort, ['sudah', 'belum'])) {
    $sort = null;
}

// Map nama bulan Indonesia ke angka
$bulanMap = [
    'Januari' => 1,
    'Februari' => 2,
    'Maret' => 3,
    'April' => 4,
    'Mei' => 5,
    'Juni' => 6,
    'Juli' => 7,
    'Agustus' => 8,
    'September' => 9,
    'Oktober' => 10,
    'November' => 11,
    'Desember' => 12,
];

// Balik map untuk mendapatkan nama bulan dari angka
$bulanReverse = array_flip($bulanMap);
$namaBulanSekarang = $bulanReverse[$bulanSekarang];

// Jika tidak ada filter di URL, default ke bulan & minggu sekarang
if (!isset($_GET['tahun']) && !isset($_GET['bulan']) && !isset($_GET['minggu'])) {
    $tahun = $tahunSekarang;
    $bulan = $namaBulanSekarang;
    $minggu = $mingguSekarang;
}

// Konversi nama bulan ke angka untuk query
$bulanAngka = $bulan ? $bulanMap[$bulan] : null;

// Reset filter bertingkat saat tahun diubah
if ($reset === 'tahun') {
    $bulan = null;
    $minggu = null;
}

// Pastikan filter bawah tidak aktif jika filter atas kosong
if (!$tahun) {
    $bulan = null;
    $minggu = null;
} elseif (!$bulan) {
    $minggu = null;
}

// Auto-isi minggu sekarang jika tahun & bulan sudah dipilih tapi minggu belum diisi
if ($tahun && $bulan && !$minggu) {
    $minggu = $mingguSekarang;
}

// Pencarian hanya aktif jika ketiga filter sudah dipilih
$searchEnabled = ($tahun && $bulan && $minggu);

// Hitung total murid aktif
$totalMurid = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) AS total FROM murid WHERE status='Aktif'
"))['total'];

// Hitung murid yang sudah bayar minggu ini
$sudahBayar = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(DISTINCT t.nisn) AS total
    FROM transaksi t
    JOIN murid m ON t.nisn = m.nisn
    WHERE t.jenis = 'Masuk'
        AND t.tahun = '$tahunSekarang'
        AND t.bulan = '$namaBulanSekarang'
        AND t.minggu = '$mingguSekarang'
        AND m.status = 'Aktif'
"))['total'] ?? 0;

// Hitung murid yang belum bayar
$belumBayar = max(0, $totalMurid - $sudahBayar);

// Hitung total uang terkumpul minggu ini
$totalUang = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT SUM(jumlah) AS total
    FROM transaksi
    WHERE jenis='Masuk'
        AND tahun = '$tahunSekarang'
        AND bulan = '$namaBulanSekarang'
        AND minggu = '$mingguSekarang'
"))['total'] ?? 0;
?>

<html>

<head>
    <title>Status Pembayaran</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel='stylesheet' href='../../assets/css/sidebar.css'>
    <link rel='stylesheet' href='../../assets/css/ketua_kelas/ketua_kelas.css'>
    <link rel='stylesheet' href='../../assets/css/ketua_kelas/status_pembayaran.css'>
</head>

<body>

    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="fw-bold">Status Pembayaran</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> |
                <?= str_replace('_', ' ', htmlspecialchars($role)) ?>
            </h6>
        </header>

        <!-- Card -->
        <div class="status-grid fade-in">
            <!-- Sudah Bayar -->
            <div class="status-card success fade-in">
                <div class="status-text">
                    <p>Sudah Bayar (Minggu Ini)</p>
                    <h2>
                        <?= $sudahBayar ?>
                    </h2>
                    <span>Dari
                        <?= $totalMurid ?> Murid
                    </span>
                </div>
                <div class="status-icon">
                    <img src="../../assets/img/check.png" width="24">
                </div>
            </div>

            <!-- Belum Bayar -->
            <div class="status-card danger fade-in">
                <div class="status-text">
                    <p>Belum Bayar (Minggu Ini)</p>
                    <h2>
                        <?= $belumBayar ?>
                    </h2>
                    <span>Dari
                        <?= $totalMurid ?> Murid
                    </span>
                </div>
                <div class="status-icon">
                    <img src="../../assets/img/uncheck.png" width="24">
                </div>
            </div>

            <!-- Total Terkumpul -->
            <div class="status-card info fade-in">
                <div class="status-text">
                    <p>Total Terkumpul</p>
                    <h2>Rp
                        <?= number_format($totalUang, 0, ',', '.') ?>
                    </h2>
                    <span>Minggu Ini</span>
                </div>
                <div class="status-icon">
                    <img src="../../assets/img/cash.png" width="24">
                </div>
            </div>

        </div>

        <!-- Bottom -->
        <form method="GET" class="filter-bar fade-in">

            <input type="hidden" name="reset" id="reset">

            <input type="text" id="searchInput" class="form-control search"
                placeholder="Cari Nama, NISN, atau Status..." <?= !$searchEnabled ? 'disabled value=""' : '' ?>
                autocomplete="off">

            <!-- Tahun -->
            <select name="tahun" class="form-select"
                onchange="document.getElementById('reset').value='tahun';this.form.submit();">
                <option selected disabled value="">Pilih Tahun</option>
                <?php for ($y = date('Y') - 1; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?= $y ?>" <?= $tahun == $y ? 'selected' : '' ?>>
                        <?= $y ?>
                    </option>
                <?php endfor; ?>
            </select>

            <!-- Bulan -->
            <select name="bulan" class="form-select" <?= !$tahun ? 'disabled' : '' ?> onchange="
        document.getElementById('reset').value='bulan';this.form.submit();">
                <option selected disabled value="">Pilih Bulan</option>
                <?php foreach ($bulanMap as $nama => $angka): ?>
                    <option value="<?= $nama ?>" <?= $bulan == $nama ? 'selected' : '' ?>>
                        <?= $nama ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Minggu -->
            <select name="minggu" class="form-select" <?= (!$tahun || !$bulan) ? 'disabled' : '' ?>
                onchange="this.form.submit()">
                <option <?= !$minggu ? 'selected' : '' ?> disabled value="">Pilih Minggu</option>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                    <option value="<?= $i ?>" <?= $minggu == $i ? 'selected' : '' ?>>
                        Minggu-
                        <?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>

            <!-- Sort By -->
            <select name="sort" class="form-select" <?= (!$tahun || !$bulan || !$minggu) ? 'disabled' : '' ?>
                onchange="this.form.submit()">
                <option value="" selected>Sort By Absen</option>
                <option value="sudah" <?= $sort === 'sudah' ? 'selected' : '' ?>>Sort By Sudah Bayar</option>
                <option value="belum" <?= $sort === 'belum' ? 'selected' : '' ?>>Sort By Belum Bayar</option>
            </select>

        </form>


        <div class="table-card fade-in">
            <table class="table-status">
                <thead>
                    <tr>
                        <th style="width:60px">No</th>
                        <th>Nama Murid</th>
                        <th style="width:160px">NISN</th>
                        <th style="width:180px">Status</th>
                    </tr>
                </thead>

                <?php
                $dataMurid = [];
                if ($tahun && $bulanAngka && $minggu) {
                    // Tentukan urutan berdasarkan pilihan sort
                    $orderBy = "m.nama ASC";
                    if ($sort === 'sudah') {
                        $orderBy = "status DESC, m.nama ASC";
                    } elseif ($sort === 'belum') {
                        $orderBy = "status ASC, m.nama ASC";
                    }

                    // Query data murid beserta status pembayaran minggu yang dipilih
                    $query = mysqli_query($conn, "
                        SELECT 
                            m.nama,
                            m.nisn,
                            IF(COUNT(t.id_transaksi) > 0, 'Sudah Bayar', 'Belum Bayar') AS status
                        FROM murid m
                        LEFT JOIN transaksi t 
                            ON m.nisn = t.nisn
                            AND t.jenis = 'Masuk'
                            AND t.tahun = '$tahun'
                            AND t.bulan = '$bulan'
                            AND t.minggu = '$minggu'
                        WHERE m.status = 'Aktif'
                        GROUP BY m.nisn
                        ORDER BY $orderBy
                    ");
                    while ($row = mysqli_fetch_assoc($query)) {
                        $dataMurid[] = $row;
                    }
                }
                ?>
                <tbody>
                    <?php if (!$tahun || !$bulan || !$minggu): ?>
                        <tr>
                            <td colspan="4" class="">
                                <div class="text-center text-secondary py-4">
                                    <i class="opacity-50">Pilih Tahun, Bulan, dan Minggu terlebih dahulu.</i>
                                </div>
                            </td>
                        </tr>

                    <?php elseif (empty($dataMurid)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted">
                                Tidak ada data
                            </td>
                        </tr>

                    <?php else: ?>
                        <?php $no = 1;
                        foreach ($dataMurid as $row): ?>
                            <tr
                                data-search="<?= strtolower(htmlspecialchars($row['nama'])) ?> <?= htmlspecialchars($row['nisn']) ?> <?= strtolower($row['status']) ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['nisn']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Sudah Bayar'): ?>
                                        <span class="status-badge success">Sudah Bayar</span>
                                    <?php else: ?>
                                        <span class="status-badge danger">Belum Bayar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <!-- Baris muncul saat hasil pencarian kosong -->
                        <tr id="noResult" style="display:none">
                            <td colspan="4">
                                <div class="text-center text-secondary py-4">
                                    <i class="opacity-50">Tidak ada hasil yang cocok.</i>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>




</body>

<script>
    const searchInput = document.getElementById('searchInput');

    if (searchInput) {
        // Cegah form submit saat Enter ditekan di input pencarian
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') e.preventDefault();
        });

        // Filter baris tabel secara real-time saat user mengetik
        searchInput.addEventListener('input', function () {
            const keyword = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.table-status tbody tr[data-search]');
            let visibleCount = 0;

            rows.forEach(row => {
                const match = row.dataset.search.includes(keyword);
                row.style.display = match ? '' : 'none';
                if (match) visibleCount++;
            });

            // Tampilkan baris "tidak ditemukan" jika semua tersembunyi
            const noResult = document.getElementById('noResult');
            if (noResult) noResult.style.display = visibleCount === 0 ? '' : 'none';
        });
    }
</script>

</html>
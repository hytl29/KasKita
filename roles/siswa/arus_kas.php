<?php
session_start();
include '../../config/connect.php';

if (!isset($_SESSION['login'])) {
    header('Location: ../../index.php');
    exit;
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// Filter
$filterJenis = $_GET['jenis'] ?? 'Semua Transaksi';
$bulanSekarang = date('F');

$bulanMapEn = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];
$bulanIndonesiaSekarang = $bulanMapEn[$bulanSekarang];
$filterBulan = $_GET['bulan'] ?? $bulanIndonesiaSekarang;
$filterTahun = $_GET['tahun'] ?? '2026';

// Map bulan Indonesia
$bulanMap = [
    'Januari' => 'Januari',
    'Februari' => 'Februari',
    'Maret' => 'Maret',
    'April' => 'April',
    'Mei' => 'Mei',
    'Juni' => 'Juni',
    'Juli' => 'Juli',
    'Agustus' => 'Agustus',
    'September' => 'September',
    'Oktober' => 'Oktober',
    'November' => 'November',
    'Desember' => 'Desember'
];

// Query dasar — filter berdasarkan WAKTU INPUT (kolom tanggal), bukan periode pembayaran
$where = "WHERE 1=1";
$params = [];

// Filter tahun berdasarkan tahun input
if ($filterTahun !== 'Semua Tahun') {
    $where .= " AND YEAR(t.tanggal) = ?";
    $params[] = $filterTahun;
}

// Filter bulan berdasarkan bulan input — konversi nama bulan ke angka
$bulanAngkaMap = [
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
if ($filterBulan !== 'Semua Bulan' && isset($bulanAngkaMap[$filterBulan])) {
    $where .= " AND MONTH(t.tanggal) = ?";
    $params[] = $bulanAngkaMap[$filterBulan];
}

// Filter jenis transaksi
if ($filterJenis !== 'Semua Transaksi') {
    $jenisMap = [
        'Pemasukan' => 'Masuk',
        'Pengeluaran' => 'Keluar'
    ];
    if (isset($jenisMap[$filterJenis])) {
        $where .= " AND t.jenis = ?";
        $params[] = $jenisMap[$filterJenis];
    }
}

// Query transaksi — digroup berdasarkan waktu input (tanggal), bukan periode pembayaran
$query = "
    SELECT 
        t.tanggal,
        t.nisn,
        t.jenis,
        t.keterangan,
        t.bulan,
        t.tahun,
        m.nama,
        SUM(t.jumlah) AS total_jumlah,
        GROUP_CONCAT(t.minggu ORDER BY t.minggu SEPARATOR ', ') AS minggu_list,
        MAX(t.dokumentasi) AS dokumentasi
    FROM transaksi t
    JOIN murid m ON t.nisn = m.nisn
    $where
    GROUP BY 
        t.tanggal, t.nisn, t.jenis
    ORDER BY t.tanggal DESC
";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("SQL Error: " . mysqli_error($conn));
}
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Hitung total transaksi
$totalTransaksi = mysqli_num_rows($result);

// Ambil saldo total summary (tidak terpengaruh filter bulan/tahun)
$qSaldoSummary = "SELECT 
                (SELECT COALESCE(SUM(jumlah), 0) FROM transaksi WHERE jenis='Masuk') - 
                (SELECT COALESCE(SUM(jumlah), 0) FROM transaksi WHERE jenis='Keluar') 
               as saldo_sekarang";
$resSaldoSummary = mysqli_query($conn, $qSaldoSummary);
$rowSaldoSummary = mysqli_fetch_assoc($resSaldoSummary);
$saldoSummary = $rowSaldoSummary['saldo_sekarang'] ?? 0;

// Hitung total pemasukan
$qMasuk = "SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE jenis='Masuk'";
$paramsMasuk = [];

if ($filterTahun !== 'Semua Tahun') {
    $qMasuk .= " AND YEAR(tanggal) = ?"; // Menggunakan YEAR dari kolom tanggal
    $paramsMasuk[] = $filterTahun;
}
if ($filterBulan !== 'Semua Bulan' && isset($bulanAngkaMap[$filterBulan])) {
    $qMasuk .= " AND MONTH(tanggal) = ?"; // Menggunakan MONTH dari kolom tanggal
    $paramsMasuk[] = $bulanAngkaMap[$filterBulan];
}

$stmtMasuk = mysqli_prepare($conn, $qMasuk);
if (!empty($paramsMasuk)) {
    $types = str_repeat('s', count($paramsMasuk));
    mysqli_stmt_bind_param($stmtMasuk, $types, ...$paramsMasuk);
}
mysqli_stmt_execute($stmtMasuk);
$resultMasuk = mysqli_stmt_get_result($stmtMasuk);
$totalMasuk = mysqli_fetch_assoc($resultMasuk)['total'] ?? 0;

// Hitung total pengeluaran
$qKeluar = "SELECT COALESCE(SUM(jumlah), 0) as total FROM transaksi WHERE jenis='Keluar'";
$paramsKeluar = [];

if ($filterTahun !== 'Semua Tahun') {
    $qKeluar .= " AND YEAR(tanggal) = ?"; // Menggunakan YEAR dari kolom tanggal
    $paramsKeluar[] = $filterTahun;
}
if ($filterBulan !== 'Semua Bulan' && isset($bulanAngkaMap[$filterBulan])) {
    $qKeluar .= " AND MONTH(tanggal) = ?"; // Menggunakan MONTH dari kolom tanggal
    $paramsKeluar[] = $filterBulan === 'Semua Bulan' ? null : $bulanAngkaMap[$filterBulan];
}

// Gunakan logika yang sama dengan Pemasukan di atas
$stmtKeluar = mysqli_prepare($conn, $qKeluar);
if (!empty($paramsKeluar)) {
    $types = str_repeat('s', count($paramsKeluar));
    mysqli_stmt_bind_param($stmtKeluar, $types, ...$paramsKeluar);
}
mysqli_stmt_execute($stmtKeluar);
$resultKeluar = mysqli_stmt_get_result($stmtKeluar);
$totalKeluar = mysqli_fetch_assoc($resultKeluar)['total'] ?? 0;

// Hitung saldo
$saldo = $saldoSummary;

// Logika label filter untuk kartu summary
$labelFilter = "";
if ($filterBulan !== 'Semua Bulan' || $filterTahun !== 'Semua Tahun') {
    $tampilBulan = ($filterBulan !== 'Semua Bulan') ? $filterBulan : "";
    $tampilTahun = ($filterTahun !== 'Semua Tahun') ? $filterTahun : "";
    $labelFilter = " (" . trim("$tampilBulan $tampilTahun") . ")";
}

// Array untuk bulan dalam setahun
$daftarBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <title>Arus Kas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel='stylesheet' href='../../assets/css/sidebar.css'>
    <link rel='stylesheet' href='../../assets/css/murid/murid.css'>
    <link rel='stylesheet' href='../../assets/css/murid/arus_kas.css'>
</head>

<body>

    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="fw-bold">Arus Kas</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> |
                <?= str_replace('_', ' ', htmlspecialchars($role)) ?>
            </h6>
        </header>

        <!-- Summary Cards -->
        <div class="summary-cards fade-in">
            <div class="summary-card saldo">
                <div class="card-content">
                    <span class="card-label">Total Saldo Kas</span>
                    <span class="card-value">Rp <?= number_format($saldo, 0, ',', '.') ?></span>
                </div>
                <div class="card-icon">
                    <img src="../../assets/img/cash.png" alt="cash">
                </div>
            </div>

            <div class="summary-card pemasukan">
                <div class="card-content">
                    <span class="card-label">Total Pemasukan<?= $labelFilter ?></span>
                    <span class="card-value">Rp <?= number_format($totalMasuk, 0, ',', '.') ?></span>
                </div>
                <div class="card-icon">
                    <img src="../../assets/img/up.png" alt="up">
                </div>
            </div>

            <div class="summary-card pengeluaran">
                <div class="card-content">
                    <span class="card-label">Total Pengeluaran<?= $labelFilter ?></span>
                    <span class="card-value">Rp <?= number_format($totalKeluar, 0, ',', '.') ?></span>
                </div>
                <div class="card-icon">
                    <img src="../../assets/img/down.png" alt="down">
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <div class="filter-wrapper">
                <div class="filter-item">
                    <img src="../../assets/img/pin.png" alt="filter" class="filter-icon">
                    <select name="jenis"
                        onchange="location.href='arus_kas.php?jenis='+this.value+'&bulan=<?= $filterBulan ?>&tahun=<?= $filterTahun ?>'">
                        <option value="Semua Transaksi" <?= $filterJenis == 'Semua Transaksi' ? 'selected' : '' ?>>Semua
                            Transaksi</option>
                        <option value="Pemasukan" <?= $filterJenis == 'Pemasukan' ? 'selected' : '' ?>>Pemasukan</option>
                        <option value="Pengeluaran" <?= $filterJenis == 'Pengeluaran' ? 'selected' : '' ?>>Pengeluaran
                        </option>
                    </select>
                </div>

                <div class="filter-item">
                    <select name="tahun"
                        onchange="location.href='arus_kas.php?jenis=<?= $filterJenis ?>&bulan=<?= $filterBulan ?>&tahun='+this.value">
                        <option value="Semua Tahun" <?= $filterTahun == 'Semua Tahun' ? 'selected' : '' ?>>Semua Tahun
                        </option>
                        <?php for ($t = 2025; $t <= 2027; $t++): ?>
                            <option value="<?= $t ?>" <?= $filterTahun == $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="filter-item">
                    <select name="bulan"
                        onchange="location.href='arus_kas.php?jenis=<?= $filterJenis ?>&tahun=<?= $filterTahun ?>&bulan='+this.value">
                        <option value="Semua Bulan" <?= $filterBulan == 'Semua Bulan' ? 'selected' : '' ?>>Semua Bulan
                        </option>
                        <?php foreach ($daftarBulan as $bulan): ?>
                            <option value="<?= $bulan ?>" <?= $filterBulan == $bulan ? 'selected' : '' ?>><?= $bulan ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="transaksi-count">
                    <span><?= $totalTransaksi ?> Transaksi</span>
                </div>
            </div>
        </div>

        <!-- Arus Kas List -->
        <div class="arus-kas-section fade-in">
            <h2 class="section-title">Arus Kas</h2>
            <div class="timeline-wrapper">
                <div class="timeline-line"></div>
                <div class="transaksi-list">
                    <?php if ($totalTransaksi > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                            $isMasuk = $row['jenis'] === 'Masuk';
                            $judul = $isMasuk ? 'Pembayaran Kas' : $row['keterangan'];
                            $tanggal = date('d F Y H:i', strtotime($row['tanggal']));
                            // Format tanggal input, lalu konversi nama bulan ke Indonesia
                            $bulanEn = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                            $bulanId = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                            $tanggal = str_replace($bulanEn, $bulanId, $tanggal);
                            $jumlah = number_format($row['total_jumlah'], 0, ',', '.');
                            $sign = $isMasuk ? '+' : '-';
                            $colorClass = $isMasuk ? 'text-success' : 'text-danger';
                            $itemClass = $isMasuk ? 'masuk' : 'keluar';
                            ?>
                            <div class="transaksi-item <?= $itemClass ?>">
                                <div class="transaksi-info">
                                    <div class="transaksi-icon">
                                        <img src="../../assets/img/<?= $isMasuk ? 'up.png' : 'down.png' ?>" alt="icon">
                                    </div>
                                    <div class="transaksi-detail">
                                        <div class="transaksi-judul"><?= htmlspecialchars($judul) ?></div>
                                        <div class="transaksi-nama"><?= htmlspecialchars($row['nama']) ?></div>
                                        <div class="transaksi-tanggal"><?= $tanggal ?></div>

                                        <?php if ($isMasuk): ?>
                                            <div class="minggu-badge">
                                                Untuk <?= $row['bulan'] ?>             <?= $row['tahun'] ?> • Minggu
                                                <span><?= $row['minggu_list'] ?></span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!$isMasuk && !empty($row['dokumentasi'])): ?>
                                            <!-- Link lihat dokumentasi pengeluaran -->
                                            <a href="../../uploads/pengeluaran/<?= htmlspecialchars($row['dokumentasi']) ?>"
                                                target="_blank" class="lihat-dokumentasi">
                                                Bukti Transaksi
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="transaksi-jumlah <?= $colorClass ?>">
                                    <?= $sign ?> Rp <?= $jumlah ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-data">Tidak ada transaksi</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Untuk mempertahankan scroll position jika perlu
        if (performance.navigation.type == 2) {
            location.reload(true);
        }
    </script>

</body>

</html>
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

// Ambil filter dari URL, default: 6 bulan terakhir & tampilan ringkasan
$periode  = $_GET['periode']  ?? '6';
$tampilan = $_GET['tampilan'] ?? 'ringkasan';

// Hitung tanggal awal berdasarkan periode yang dipilih
$bulanLalu = (int) $periode;
$tanggalMulai = date('Y-m-d', strtotime("-{$bulanLalu} months"));

// Hitung total pemasukan dalam periode filter
$qMasuk = mysqli_query($conn, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM transaksi
    WHERE jenis = 'Masuk'
      AND tanggal >= '$tanggalMulai'
");
$totalMasuk = (int) mysqli_fetch_assoc($qMasuk)['total'];

// Hitung total pengeluaran dalam periode filter
$qKeluar = mysqli_query($conn, "
    SELECT COALESCE(SUM(jumlah), 0) AS total
    FROM transaksi
    WHERE jenis = 'Keluar'
      AND tanggal >= '$tanggalMulai'
");
$totalKeluar = (int) mysqli_fetch_assoc($qKeluar)['total'];

// Saldo bersih = akumulatif semua transaksi (bukan hanya periode filter)
$qSaldoTotal = mysqli_query($conn, "
    SELECT
        COALESCE(SUM(CASE WHEN jenis = 'Masuk'  THEN jumlah ELSE 0 END), 0) -
        COALESCE(SUM(CASE WHEN jenis = 'Keluar' THEN jumlah ELSE 0 END), 0) AS saldo
    FROM transaksi
");
$saldoBersih = (int) mysqli_fetch_assoc($qSaldoTotal)['saldo'];

// Hitung persentase perubahan pemasukan vs periode sebelumnya
$tanggalSebelumnya = date('Y-m-d', strtotime("-{$bulanLalu} months", strtotime($tanggalMulai)));
$qMasukPrev = mysqli_query($conn, "
    SELECT COALESCE(SUM(jumlah), 0) AS total FROM transaksi
    WHERE jenis = 'Masuk' AND tanggal >= '$tanggalSebelumnya' AND tanggal < '$tanggalMulai'
");
$totalMasukPrev = (int) mysqli_fetch_assoc($qMasukPrev)['total'];
$pctMasuk = $totalMasukPrev > 0
    ? round((($totalMasuk - $totalMasukPrev) / $totalMasukPrev) * 100)
    : ($totalMasuk > 0 ? 100 : 0);

// Hitung persentase perubahan pengeluaran vs periode sebelumnya
$qKeluarPrev = mysqli_query($conn, "
    SELECT COALESCE(SUM(jumlah), 0) AS total FROM transaksi
    WHERE jenis = 'Keluar' AND tanggal >= '$tanggalSebelumnya' AND tanggal < '$tanggalMulai'
");
$totalKeluarPrev = (int) mysqli_fetch_assoc($qKeluarPrev)['total'];
$pctKeluar = $totalKeluarPrev > 0
    ? round((($totalKeluar - $totalKeluarPrev) / $totalKeluarPrev) * 100)
    : ($totalKeluar > 0 ? 100 : 0);

// Pemasukan per bulan (grouping by bulan untuk bar chart kategori)
$qMasukKat = mysqli_query($conn, "
    SELECT bulan AS kategori, SUM(jumlah) AS total
    FROM transaksi
    WHERE jenis = 'Masuk' AND tanggal >= '$tanggalMulai'
    GROUP BY bulan
    ORDER BY MIN(tanggal) ASC
");
$dataMasukKat = [];
while ($r = mysqli_fetch_assoc($qMasukKat)) $dataMasukKat[] = $r;

// Pengeluaran per kategori (keterangan)
$qKeluarKat = mysqli_query($conn, "
    SELECT keterangan AS kategori, SUM(jumlah) AS total
    FROM transaksi
    WHERE jenis = 'Keluar' AND tanggal >= '$tanggalMulai'
    GROUP BY keterangan
    ORDER BY total DESC
");
$dataKeluarKat = [];
while ($r = mysqli_fetch_assoc($qKeluarKat)) $dataKeluarKat[] = $r;

// Data per bulan untuk tabel laporan detail bulanan
$qDetail = mysqli_query($conn, "
    SELECT
        DATE_FORMAT(tanggal, '%b %Y') AS label,
        DATE_FORMAT(tanggal, '%Y-%m') AS sort_key,
        SUM(CASE WHEN jenis = 'Masuk'  THEN jumlah ELSE 0 END) AS masuk,
        SUM(CASE WHEN jenis = 'Keluar' THEN jumlah ELSE 0 END) AS keluar
    FROM transaksi
    WHERE tanggal >= '$tanggalMulai'
    GROUP BY DATE_FORMAT(tanggal, '%Y-%m')
    ORDER BY sort_key ASC
");
$dataDetail = [];
while ($r = mysqli_fetch_assoc($qDetail)) $dataDetail[] = $r;

// Data detail: pemasukan per murid (hanya untuk mode detail)
$qDetailMasuk = mysqli_query($conn, "
    SELECT
        m.nama,
        t.nisn,
        t.bulan,
        t.tahun,
        SUM(t.jumlah) AS total,
        GROUP_CONCAT(t.minggu ORDER BY t.minggu SEPARATOR ', ') AS minggu_list,
        t.tanggal
    FROM transaksi t
    JOIN murid m ON t.nisn = m.nisn
    WHERE t.jenis = 'Masuk' AND t.tanggal >= '$tanggalMulai'
    GROUP BY t.nisn, t.tanggal, t.bulan, t.tahun
    ORDER BY t.tanggal DESC
");
$dataDetailMasuk = [];
while ($r = mysqli_fetch_assoc($qDetailMasuk)) $dataDetailMasuk[] = $r;

// Data detail: pengeluaran per transaksi (hanya untuk mode detail)
$qDetailKeluar = mysqli_query($conn, "
    SELECT t.tanggal, t.keterangan, t.jumlah, t.dokumentasi, m.nama
    FROM transaksi t
    JOIN murid m ON t.nisn = m.nisn
    WHERE t.jenis = 'Keluar' AND t.tanggal >= '$tanggalMulai'
    ORDER BY t.tanggal DESC
");
$dataDetailKeluar = [];
while ($r = mysqli_fetch_assoc($qDetailKeluar)) $dataDetailKeluar[] = $r;

// Format rupiah
function rupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// Konversi nama bulan Inggris ke Indonesia
function tglId($tgl) {
    $en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    $id = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return str_replace($en, $id, date('d F Y H:i', strtotime($tgl)));
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <title>Laporan Kas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/bendahara/bendahara.css">
    <link rel="stylesheet" href="../../assets/css/bendahara/laporan_kas.css">
</head>

<body>

    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">

        <!-- Header halaman -->
        <header class="page-header">
            <h3 class="fw-bold">Laporan Kas</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> | <?= htmlspecialchars($role) ?></h6>
        </header>

        <!-- Bar filter periode dan tampilan — style sama dengan arus kas -->
        <div class="filter-section fade-in">
            <div class="filter-wrapper">

                <!-- Filter ikon pin -->
                <div class="filter-item">
                    <img src="../../assets/img/pin.png" alt="filter" class="filter-icon">
                    <select id="filterTampilan" onchange="applyFilter()">
                        <option value="ringkasan" <?= $tampilan == 'ringkasan' ? 'selected' : '' ?>>Ringkasan</option>
                        <option value="detail"    <?= $tampilan == 'detail'    ? 'selected' : '' ?>>Detail</option>
                    </select>
                </div>

                <!-- Filter periode -->
                <div class="filter-item">
                    <select id="filterPeriode" onchange="applyFilter()">
                        <option value="1"  <?= $periode == '1'  ? 'selected' : '' ?>>1 Bulan Terakhir</option>
                        <option value="3"  <?= $periode == '3'  ? 'selected' : '' ?>>3 Bulan Terakhir</option>
                        <option value="6"  <?= $periode == '6'  ? 'selected' : '' ?>>6 Bulan Terakhir</option>
                        <option value="12" <?= $periode == '12' ? 'selected' : '' ?>>1 Tahun Terakhir</option>
                    </select>
                </div>

                <!-- Tombol cetak laporan -->
                <button onclick="window.print()" class="btn-cetak">
                    <img src="../../assets/img/laporan.png" alt="cetak">
                    Cetak
                </button>

            </div>
        </div>

        <!-- Grid 4 kartu ringkasan -->
        <div class="laporan-grid">

            <!-- Kartu Saldo Bersih -->
            <div class="laporan-card saldo fade-in">
                <div class="laporan-card-icon">
                    <img src="../../assets/img/wallet.png" alt="Saldo">
                </div>
                <span class="laporan-card-badge">Total</span>
                <div class="laporan-card-label">Saldo Bersih</div>
                <div class="laporan-card-value"><?= rupiah($saldoBersih) ?></div>
            </div>

            <!-- Kartu Total Pemasukan -->
            <div class="laporan-card masuk fade-in">
                <div class="laporan-card-icon">
                    <img src="../../assets/img/up.png" alt="Pemasukan">
                </div>
                <div class="laporan-card-label">Total Pemasukan</div>
                <div class="laporan-card-value"><?= rupiah($totalMasuk) ?></div>
            </div>

            <!-- Kartu Total Pengeluaran -->
            <div class="laporan-card keluar fade-in">
                <div class="laporan-card-icon">
                    <img src="../../assets/img/down.png" alt="Pengeluaran">
                </div>
                <div class="laporan-card-label">Total Pengeluaran</div>
                <div class="laporan-card-value"><?= rupiah($totalKeluar) ?></div>
            </div>

        </div>

        <!-- Grid 2 kolom: pemasukan & pengeluaran per kategori -->
        <div class="kategori-grid fade-in">

            <!-- Pemasukan per Bulan -->
            <div class="kategori-card fade-in">
                <h5 class="kategori-title">Pemasukan Per Bulan</h5>

                <?php if (empty($dataMasukKat)): ?>
                    <p class="text-muted text-center py-3" style="font-style:italic">Belum ada data</p>
                <?php else: ?>
                    <?php foreach ($dataMasukKat as $item):
                        $pct = $totalMasuk > 0 ? round(($item['total'] / $totalMasuk) * 100) : 0;
                    ?>
                        <div class="kategori-item">
                            <div class="kategori-row">
                                <span class="kategori-nama"><?= htmlspecialchars($item['kategori']) ?></span>
                                <span class="kategori-nominal"><?= rupiah($item['total']) ?></span>
                            </div>
                            <div class="kategori-bar-wrap">
                                <!-- Bar hijau untuk pemasukan -->
                                <div class="kategori-bar bar-masuk" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="kategori-pct"><?= $pct ?>%</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pengeluaran per Kategori -->
            <div class="kategori-card fade-in">
                <h5 class="kategori-title">Pengeluaran Per Kategori</h5>

                <?php if (empty($dataKeluarKat)): ?>
                    <p class="text-muted text-center py-3" style="font-style:italic">Belum ada data</p>
                <?php else: ?>
                    <?php foreach ($dataKeluarKat as $item):
                        $pct = $totalKeluar > 0 ? round(($item['total'] / $totalKeluar) * 100) : 0;
                    ?>
                        <div class="kategori-item">
                            <div class="kategori-row">
                                <span class="kategori-nama"><?= htmlspecialchars($item['kategori']) ?></span>
                                <span class="kategori-nominal"><?= rupiah($item['total']) ?></span>
                            </div>
                            <div class="kategori-bar-wrap">
                                <!-- Bar merah untuk pengeluaran -->
                                <div class="kategori-bar bar-keluar" style="width: <?= $pct ?>%"></div>
                            </div>
                            <div class="kategori-pct"><?= $pct ?>%</div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- Tabel Laporan Detail Bulanan -->
        <div class="detail-card fade-in">
            <h5 class="detail-title">Laporan Detail Bulanan</h5>

            <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Bulan</th>
                        <th>Pemasukan</th>
                        <th>Pengeluaran</th>
                        <th>Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataDetail)): ?>
                        <tr>
                            <td colspan="4" style="text-align:center;font-style:italic;color:#94a3b8;padding:32px">
                                Belum ada data
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dataDetail as $row):
                            $saldoBulan = $row['masuk'] - $row['keluar'];
                        ?>
                            <tr>
                                <td class="col-bulan"><?= $row['label'] ?></td>
                                <td class="col-masuk">+ <?= rupiah($row['masuk']) ?></td>
                                <td class="col-keluar">- <?= rupiah($row['keluar']) ?></td>
                                <td class="col-saldo"><?= rupiah($saldoBulan) ?></td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Baris total -->
                        <tr class="row-total">
                            <td>TOTAL</td>
                            <td class="col-masuk"><?= rupiah($totalMasuk) ?></td>
                            <td class="col-keluar"><?= rupiah($totalKeluar) ?></td>
                            <td class="col-saldo"><?= rupiah($saldoBersih) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php if ($tampilan === 'detail'): ?>

        <!-- Detail Pemasukan per Murid -->
        <div class="detail-card fade-in">
            <h5 class="detail-title">Detail Pemasukan per Murid</h5>
            <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Tanggal Input</th>
                        <th>Nama Murid</th>
                        <th>Untuk Bulan</th>
                        <th>Minggu</th>
                        <th>Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataDetailMasuk)): ?>
                        <tr><td colspan="5" style="text-align:center;font-style:italic;color:#94a3b8;padding:32px">Belum ada data</td></tr>
                    <?php else: ?>
                        <?php foreach ($dataDetailMasuk as $r): ?>
                            <tr>
                                <td class="col-bulan"><?= tglId($r['tanggal']) ?></td>
                                <td><?= htmlspecialchars($r['nama']) ?></td>
                                <td><?= htmlspecialchars($r['bulan']) ?> <?= $r['tahun'] ?></td>
                                <td style="text-align:center">
                                    <span class="badge-minggu-detail">Minggu <?= $r['minggu_list'] ?></span>
                                </td>
                                <td class="col-masuk">+ <?= rupiah($r['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="row-total">
                            <td colspan="4">TOTAL</td>
                            <td class="col-masuk"><?= rupiah($totalMasuk) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Detail Pengeluaran -->
        <div class="detail-card fade-in">
            <h5 class="detail-title">Detail Pengeluaran</h5>
            <div class="detail-table-wrap">
            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th style="text-align:center">Kategori</th>
                        <th style="text-align:center">Di-Input Oleh</th>
                        <th style="text-align:center">Dokumentasi</th>
                        <th style="text-align:center">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataDetailKeluar)): ?>
                        <tr><td colspan="5" style="text-align:center;font-style:italic;color:#94a3b8;padding:32px">Belum ada data</td></tr>
                    <?php else: ?>
                        <?php foreach ($dataDetailKeluar as $r): ?>
                            <tr>
                                <td class="col-bulan"><?= tglId($r['tanggal']) ?></td>
                                <td style="text-align:center"><?= htmlspecialchars($r['keterangan']) ?></td>
                                <td style="text-align:center"><?= htmlspecialchars($r['nama']) ?></td>
                                <td style="text-align:center">
                                    <?php if (!empty($r['dokumentasi'])): ?>
                                        <a href="../../uploads/pengeluaran/<?= htmlspecialchars($r['dokumentasi']) ?>"
                                           target="_blank" class="lihat-dok-link">Lihat</a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;font-size:12px">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-keluar" style="text-align:center">- <?= rupiah($r['jumlah']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="row-total">
                            <td colspan="4">TOTAL</td>
                            <td class="col-keluar" style="text-align:center"><?= rupiah($totalKeluar) ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <?php endif; ?>

    </div>

    <script>
        // Terapkan filter dengan redirect ke URL baru
        function applyFilter() {
            const periode  = document.getElementById('filterPeriode').value;
            const tampilan = document.getElementById('filterTampilan').value;
            window.location.href = `laporan_kas.php?periode=${periode}&tampilan=${tampilan}`;
        }
    </script>

</body>
</html>

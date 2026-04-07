<?php
session_start();
include '../../config/connect.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
$nisn_user = $_SESSION['nisn'];

// Daftar bulan untuk looping
$listBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

// Hitung total pemasukan
$qMasuk = mysqli_query(
    $conn,
    "SELECT SUM(jumlah) AS total FROM transaksi WHERE jenis='Masuk'"
);
$masuk = mysqli_fetch_assoc($qMasuk)['total'] ?? 0;

// Hitung total pengeluaran
$qKeluar = mysqli_query(
    $conn,
    "SELECT SUM(jumlah) AS total FROM transaksi WHERE jenis='Keluar'"
);
$keluar = mysqli_fetch_assoc($qKeluar)['total'] ?? 0;

// Hitung saldo (pemasukan - pengeluaran)
$saldo = $masuk - $keluar;

// Minggu dan tahun saat ini untuk filter pembayaran
$mingguNow = date('W');
$tahunNow = date('Y');

// Hitung murid yang sudah bayar minggu ini
$qSudah = mysqli_query($conn, "
    SELECT COUNT(DISTINCT nisn) AS total
    FROM transaksi
    WHERE jenis = 'Masuk'
      AND bulan = MONTHNAME(NOW())
      AND tahun = YEAR(NOW())
      AND minggu = '$mingguNow'
");
$sudahBayar = mysqli_fetch_assoc($qSudah)['total'] ?? 0;

// Hitung total murid aktif
$qTotal = mysqli_query($conn, "
    SELECT COUNT(*) AS total
    FROM murid
    WHERE status = 'Aktif'
");
$totalMurid = mysqli_fetch_assoc($qTotal)['total'] ?? 0;

// Murid yang belum bayar
$belumBayar = max(0, $totalMurid - $sudahBayar);

// Aktivitas Terbaru
$qAktivitas = mysqli_query($conn, "
    SELECT *
    FROM (

        -- PEMASUKAN (DIGABUNG PER 1 SUBMIT)
        SELECT 
            'Masuk' AS jenis,
            t.nisn,
            m.nama,
            t.tanggal,
            t.bulan,
            t.tahun,
            SUM(t.jumlah) AS total_jumlah,
            GROUP_CONCAT(t.minggu ORDER BY t.minggu ASC) AS minggu_list,
            'Pembayaran Kas' AS judul
        FROM transaksi t
        JOIN murid m ON t.nisn = m.nisn
        WHERE t.jenis = 'Masuk'
        GROUP BY t.nisn, t.tanggal, t.bulan, t.tahun

        UNION ALL

        -- PENGELUARAN (1 ROW = 1 AKTIVITAS)
        SELECT
            'Keluar' AS jenis,
            t.nisn,
            m.nama,
            t.tanggal,
            t.bulan,
            t.tahun,
            t.jumlah AS total_jumlah,
            NULL AS minggu_list,
            t.keterangan AS judul
        FROM transaksi t
        JOIN murid m ON t.nisn = m.nisn
        WHERE t.jenis = 'Keluar'

    ) aktivitas
    ORDER BY tanggal DESC
    LIMIT 5
");

// Ambil data pembayaran user untuk tahun ini
$qCheckBayar = mysqli_query($conn, "
    SELECT bulan, minggu 
    FROM transaksi 
    WHERE nisn = '$nisn_user' 
      AND jenis = 'Masuk' 
      AND tahun = '$tahunNow'
");

// Masukkan ke dalam array agar mudah dicek
$dataBayar = [];
while ($row = mysqli_fetch_assoc($qCheckBayar)) {
    $dataBayar[$row['bulan']][] = (int) $row['minggu'];
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/murid/murid.css">
    <link rel="stylesheet" href="../../assets/css/murid/dashboard.css">
</head>

<body>

    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="fw-bold">Dashboard</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> |
                <?= str_replace('_', ' ', htmlspecialchars($role)) ?>
            </h6>
        </header>

        <div class="dashboard-grid">
            <!-- Saldo -->
            <div class="card-summary saldo fade-in">
                <div class="icon-circle">
                    <img src="../../assets/img/wallet.png" alt="Saldo">
                </div>
                <p>Saldo Kas</p>
                <h4>Rp <?= number_format($saldo, 0, ',', '.') ?></h4>
            </div>

            <!-- Pemasukan -->
            <div class="card-summary masuk fade-in">
                <div class="icon-circle">
                    <img src="../../assets/img/up.png" alt="Masuk">
                </div>
                <p>Total Pemasukan</p>
                <h4>Rp <?= number_format($masuk, 0, ',', '.') ?></h4>
            </div>

            <!-- Pengeluaran -->
            <div class="card-summary keluar fade-in">
                <div class="icon-circle">
                    <img src="../../assets/img/down.png" alt="Keluar">
                </div>
                <p>Total Pengeluaran</p>
                <h4>Rp <?= number_format($keluar, 0, ',', '.') ?></h4>
            </div>

        </div>
        <div class="dashboard-bottom">

            <!-- Aktivitas Terbaru -->
            <div class="activity-card fade-in">
                <div class="activity-header">
                    <h5 class="fw-bold">Aktivitas Terbaru</h5>
                    <a href="arus_kas.php" class="btn btn-sm btn-gradient">Lihat Detail</a>
                </div>

                <?php if (mysqli_num_rows($qAktivitas) > 0): ?>
                    <?php while ($a = mysqli_fetch_assoc($qAktivitas)): ?>
                        <div class="activity-item">
                            <div class="activity-left">
                                <div class="activity-icon <?= $a['jenis'] == 'Masuk' ? 'in' : 'out' ?>">
                                    <img src="../../assets/img/<?= $a['jenis'] == 'Masuk' ? 'up.png' : 'down.png' ?>">
                                </div>

                                <div class="activity-text">
                                    <strong><?= htmlspecialchars($a['judul']) ?></strong>

                                    <div class="activity-meta">
                                        <small>
                                            <?= htmlspecialchars($a['nama']) ?> |
                                            <?= date('d M Y H:i', strtotime($a['tanggal'])) ?>
                                        </small>

                                        <?php if ($a['jenis'] == 'Masuk'): ?>
                                            <span class="badge-minggu">
                                                Untuk <?= $a['bulan'] ?>             <?= $a['tahun'] ?> · Minggu <?= $a['minggu_list'] ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="activity-amount <?= $a['jenis'] == 'Masuk' ? 'plus' : 'minus' ?>">
                                <?= $a['jenis'] == 'Masuk' ? '+' : '-' ?>
                                Rp <?= number_format($a['total_jumlah'], 0, ',', '.') ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center text-secondary py-4">
                        <i class="opacity-50">Belum ada aktivitas.</i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="activity-card fade-in">
                <div class="activity-header">
                    <h5 class="fw-bold">Status Kas Anda (<?= $tahunNow ?>)</h5>
                </div>

                <div class="table-card" style="margin-top: 0; box-shadow: none; border: 1px solid #eef2f7;">
                    <table class="table-status">
                        <thead>
                            <tr>
                                <th rowspan="2" style="vertical-align: middle;">Bulan</th>
                                <th colspan="4">Minggu</th>
                            </tr>
                            <tr>
                                <th style="font-size: 11px; padding: 5px;">1</th>
                                <th style="font-size: 11px; padding: 5px;">2</th>
                                <th style="font-size: 11px; padding: 5px;">3</th>
                                <th style="font-size: 11px; padding: 5px;">4</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listBulan as $bln): ?>
                                <tr>
                                    <td style="text-align: left; font-weight: 600; padding-left: 15px;"><?= $bln ?></td>
                                    <?php for ($m = 1; $m <= 4; $m++): ?>
                                        <td class="text-center">
                                            <?php
                                            // Pengecekan: Apakah bulan ini ada di array DAN apakah minggu ini sudah dibayar?
                                            $isPaid = isset($dataBayar[$bln]) && in_array($m, $dataBayar[$bln]);

                                            if ($isPaid): ?>
                                                <span style="color: #16a34a; font-weight: bold;">✔</span>
                                            <?php else: ?>
                                                <span style="color: #dc2626; font-weight: bold; opacity: 0.2;">✘</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
</body>

</html>
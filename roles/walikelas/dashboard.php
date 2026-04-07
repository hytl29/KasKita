<?php
session_start();
include '../../config/connect.php';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

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


?>

<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
    <link rel="stylesheet" href="../../assets/css/walikelas/walikelas.css">
    <link rel="stylesheet" href="../../assets/css/walikelas/dashboard.css">
</head>

<body>

    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="fw-bold">Dashboard</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> | <?= htmlspecialchars($role) ?></h6>
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

            <!-- Sudah Bayar -->
            <a href="status_pembayaran.php" class="card-link">
                <div class="card-summary bayar fade-in">
                    <div class="icon-circle">
                        <img src="../../assets/img/person.png" alt="Murid">
                    </div>
                    <p>Sudah Bayar (Minggu Ini)</p>
                    <h4>
                        <?= $sudahBayar ?> /
                        <?= $totalMurid ?> Murid
                    </h4>
                </div>
            </a>
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

            <div class="chart-card fade-in">
                <h5 class="fw-bold text-center mb-3">
                    Status Pembayaran<br>
                    <small class="text-muted">(Minggu Ini)</small>
                </h5>

                <canvas id="statusChart" width="360" height="360"></canvas>

                <div class="chart-legend">
                    <div>
                        <span class="dot belum"></span>
                        Belum Bayar <br>
                        <small>
                            <?= $belumBayar ?> Murid
                        </small>
                    </div>
                    <div>
                        <span class="dot sudah"></span>
                        Sudah Bayar <br>
                        <small>
                            <?= $sudahBayar ?> Murid
                        </small>
                    </div>
                </div>
            </div>
        </div>



    </div>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const canvas = document.getElementById('statusChart');
        const ctx = canvas.getContext('2d');

        const gradientSudah = ctx.createLinearGradient(0, 0, 0, canvas.height);
        gradientSudah.addColorStop(0, '#D1FAE5');
        gradientSudah.addColorStop(1, '#A7F3D0');

        const gradientBelum = ctx.createLinearGradient(0, 0, 0, canvas.height);
        gradientBelum.addColorStop(0, '#FFCBCB');
        gradientBelum.addColorStop(1, '#FF9A9A');

        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Belum Bayar', 'Sudah Bayar'],
                datasets: [{
                    data: [<?= $belumBayar ?>, <?= $sudahBayar ?>],
                    backgroundColor: [gradientBelum, gradientSudah],
                    borderWidth: 4,
                    hoverOffset: 8
                }]
            },
            options: {
                cutout: '75%',
                layout: {
                    padding: 12
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>

</html>
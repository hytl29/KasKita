<?php
include '../../config/connect.php';

// Endpoint: cari tahun aktif pembayaran murid (satu arah)
// Kembalikan tahun pertama yang belum lunas (belum 48 minggu terbayar)
$nisn = $_POST['nisn'] ?? '';

if (!$nisn) {
    echo json_encode(['next_tahun' => 2025]);
    exit;
}

// Ambil semua tahun yang pernah ada transaksi untuk murid ini, dari terlama
$q = mysqli_query($conn, "
    SELECT tahun, COUNT(DISTINCT CONCAT(bulan, '-', minggu)) AS total_minggu
    FROM transaksi
    WHERE nisn = '$nisn' AND jenis = 'Masuk'
    GROUP BY tahun
    ORDER BY tahun ASC
");

// Cari tahun pertama yang belum lunas (< 48 minggu)
while ($row = mysqli_fetch_assoc($q)) {
    if ((int) $row['total_minggu'] < 48) {
        echo json_encode(['next_tahun' => (int) $row['tahun']]);
        exit;
    }
}

// Murid belum pernah bayar sama sekali — mulai dari tahun awal sistem (2025)
// Jika semua tahun sudah lunas — lanjut ke tahun berikutnya setelah tahun terakhir
$qTerakhir = mysqli_query($conn, "
    SELECT MAX(tahun) AS tahun_terakhir
    FROM transaksi
    WHERE nisn = '$nisn' AND jenis = 'Masuk'
");
$rowTerakhir = mysqli_fetch_assoc($qTerakhir);

if ($rowTerakhir['tahun_terakhir']) {
    // Semua tahun lunas, lanjut ke tahun berikutnya
    echo json_encode(['next_tahun' => (int) $rowTerakhir['tahun_terakhir'] + 1]);
} else {
    // Belum pernah bayar sama sekali, mulai dari 2025
    echo json_encode(['next_tahun' => 2025]);
}

<?php
include '../../config/connect.php';

$nisn = $_POST['nisn'];
$tahun = $_POST['tahun'];

$daftarBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

$nextBulan = 'Januari'; // Default
$mingguLunas = [];

// Cari bulan terakhir yang ada transaksinya di tahun tersebut
$q = mysqli_query($conn, "SELECT bulan, COUNT(DISTINCT minggu) as total_minggu 
                          FROM transaksi 
                          WHERE nisn='$nisn' AND tahun='$tahun' AND jenis='Masuk'
                          GROUP BY bulan 
                          ORDER BY FIELD(bulan, 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember') DESC 
                          LIMIT 1");

if (mysqli_num_rows($q) > 0) {
    $data = mysqli_fetch_assoc($q);
    $bulanTerakhir = $data['bulan'];
    $jmlMinggu = (int)$data['total_minggu'];

    if ($jmlMinggu >= 4) {
        // Jika bulan tersebut sudah lunas (4 minggu), cari index bulan berikutnya
        $currIdx = array_search($bulanTerakhir, $daftarBulan);
        if ($currIdx !== false && $currIdx < 11) {
            $nextBulan = $daftarBulan[$currIdx + 1];
        } else {
            $nextBulan = "Tahun Sudah Penuh";
        }
    } else {
        // Jika belum lunas, tetap di bulan tersebut
        $nextBulan = $bulanTerakhir;
        // Ambil list minggu yang sudah dibayar di bulan tersebut
        $qM = mysqli_query($conn, "SELECT minggu FROM transaksi WHERE nisn='$nisn' AND tahun='$tahun' AND bulan='$nextBulan' AND jenis='Masuk'");
        while($rm = mysqli_fetch_assoc($qM)) {
            $mingguLunas[] = (int)$rm['minggu'];
        }
    }
}

echo json_encode([
    'next_bulan' => $nextBulan,
    'minggu_lunas' => $mingguLunas
]);
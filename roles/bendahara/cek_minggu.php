<?php
// Endpoint AJAX: cek minggu/bulan yang sudah dibayar oleh murid tertentu
include '../../config/connect.php';

$nisn  = $_POST['nisn']  ?? '';
$tahun = $_POST['tahun'] ?? '';
$bulan = $_POST['bulan'] ?? '';
$mode  = $_POST['mode']  ?? 'minggu';

$data = [];

if ($mode === 'bulan') {
    // Mode bulan: kembalikan object { lunas: [...], minggu_per_bulan: {Januari: 2, ...} }
    if ($nisn && $tahun) {
        $q = mysqli_query($conn, "
            SELECT bulan, COUNT(DISTINCT minggu) AS total_minggu
            FROM transaksi
            WHERE nisn = '$nisn' AND tahun = '$tahun' AND jenis = 'Masuk'
            GROUP BY bulan
        ");
        $lunas = [];
        $mingguPerBulan = [];
        while ($row = mysqli_fetch_assoc($q)) {
            $mingguPerBulan[$row['bulan']] = (int) $row['total_minggu'];
            if ((int) $row['total_minggu'] >= 4) {
                $lunas[] = $row['bulan'];
            }
        }
        echo json_encode(['lunas' => $lunas, 'minggu_per_bulan' => $mingguPerBulan]);
    } else {
        echo json_encode(['lunas' => [], 'minggu_per_bulan' => []]);
    }
} else {
    // Mode minggu (default): kembalikan array nomor minggu yang sudah dibayar
    if ($nisn && $tahun && $bulan) {
        $q = mysqli_query($conn, "
            SELECT minggu
            FROM transaksi
            WHERE nisn = '$nisn' AND tahun = '$tahun' AND bulan = '$bulan' AND jenis = 'Masuk'
        ");
        while ($row = mysqli_fetch_assoc($q)) {
            $data[] = (int) $row['minggu'];
        }
    }
    echo json_encode($data);
}

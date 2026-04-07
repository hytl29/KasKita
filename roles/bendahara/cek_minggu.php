<?php
// Endpoint AJAX: cek minggu mana yang sudah dibayar oleh murid tertentu
include '../../config/connect.php';

$nisn  = $_POST['nisn'] ?? '';
$tahun = $_POST['tahun'] ?? '';
$bulan = $_POST['bulan'] ?? '';

$data = [];

if ($nisn && $tahun && $bulan) {

    $query = mysqli_query($conn, "
        SELECT minggu 
        FROM transaksi
        WHERE nisn='$nisn'
        AND tahun='$tahun'
        AND bulan='$bulan'
        AND jenis='Masuk'
    ");

    while ($row = mysqli_fetch_assoc($query)) {
        $data[] = (int)$row['minggu'];
    }
}

echo json_encode($data);


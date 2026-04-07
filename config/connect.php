<?php
// Konfigurasi koneksi database
$host = "localhost";
$user = "root";
$pass = "";
$db = "db_kas";

$conn = mysqli_connect($host, $user, $pass, $db);

// Hentikan eksekusi jika koneksi gagal
if (!$conn) {
    die("Koneksi database gagal!");
}

<?php
session_start();

// Cek apakah sudah login
if (!isset($_SESSION['login'])) {
    header("Location: ../auth/login.php");
    exit;
}

$redirect = '';

// Tentukan halaman tujuan berdasarkan role
if (isset($_SESSION['nisn'])) {
    if ($_SESSION['role'] == 'Siswa') {
        $redirect = '../roles/siswa/dashboard.php';
    } elseif ($_SESSION['role'] == 'Bendahara') {
        $redirect = '../roles/bendahara/dashboard.php';
    } elseif ($_SESSION['role'] == 'Ketua_Kelas') {
        $redirect = '../roles/ketua_kelas/dashboard.php';
    }
// Wali Kelas menggunakan NIK
} elseif (isset($_SESSION['nik'])) {
    $redirect = '../roles/walikelas/dashboard.php';
}

// Jika role tidak dikenali, logout
if ($redirect == '') {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Memuat Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <div id="loader">
        <div class="spinner-border text-primary"></div>
    </div>

    <script>
        setTimeout(() => {
            window.location.href = "<?= $redirect ?>";
        }, 2000); 
    </script>
</body>
</html>
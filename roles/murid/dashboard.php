<?php
session_start();
include '../../config/connect.php';

if (!isset($_SESSION['login'])) {
    header('Location: ../../index.php');
    exit;
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
?>

<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
    <link rel='stylesheet' href='../../assets/css/style.css'>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

    <?php include '../../reuse/sidebar.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="text-dark fw-bold">Dashboard</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> | <?= htmlspecialchars($role) ?></h6>
        </header>
        
        <div class="page-inner">
            <!-- Dashboard content here -->
        </div>
    </div>
    
</body>

</html>
<?php
// Cek session, redirect ke login jika belum masuk
if (!isset($_SESSION['login'])) {
    header('Location: ../../index.php');
    exit;
}

$role = $_SESSION['role'];
$page = basename($_SERVER['PHP_SELF']);

// Tandai menu aktif berdasarkan nama file halaman saat ini
function active($file)
{
    global $page;
    return $page === $file ? 'active' : '';
}
?>

<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <img src="../../assets/img/wallet.png" alt="Logo KasKita">
        </div>
        <span class="logo-text">KasKita</span>
    </div>

    <ul class="sidebar-menu">
        <!-- Dashboard (semua role) -->
        <li class="<?= active('dashboard.php') ?>">
            <a href="../../roles/<?= $role ?>/dashboard.php">
                <img src="../../assets/img/dashboard.png" alt="Dashboard">
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Data Murid (walikelas saja) -->
        <?php if ($role === 'Walikelas'): ?>
            <li class="<?= active('data_murid.php') ?>">
                <a href="../../roles/walikelas/data_murid.php">
                    <img src="../../assets/img/person.png" alt="Data Murid">
                    <span>Data Murid</span>
                </a>
            </li>
        <?php endif; ?>

        <!-- Buat Transaksi (bendahara saja) -->
        <?php if ($role === 'Bendahara'): ?>
            <li class="<?= active('buat_transaksi.php') ?>">
                <a href="../../roles/bendahara/buat_transaksi.php?pemasukan">
                    <img src="../../assets/img/buattransaksi.png" alt="Buat Transaksi">
                    <span>Buat Transaksi</span>
                </a>
            </li>
        <?php endif; ?>

        <!-- Status Pembayaran (bendahara, walikelas, ketua kelas) -->
         <?php if (in_array($role, ['Bendahara', 'Walikelas', 'Ketua Kelas'])): ?>
        <li class="<?= active('status_pembayaran.php') ?>">
            <a href="../../roles/<?= $role ?>/status_pembayaran.php">
                <img src="../../assets/img/status.png" alt="Status Pembayaran">
                <span>Status Pembayaran</span>
            </a>
        </li>
         <?php endif; ?>

        <!-- Arus Kas (bendahara, walikelas, ketua kelas) -->
        <?php if (in_array($role, ['Bendahara', 'Walikelas', 'Ketua Kelas'])): ?>
            <li class="<?= active('arus_kas.php') ?>">
                <a href="../../roles/<?= $role ?>/arus_kas.php">
                    <img src="../../assets/img/aruskas.png" alt="Arus Kas">
                    <span>Arus Kas</span>
                </a>
            </li>
        <?php endif; ?>

        <!-- Laporan Kas (bendahara, walikelas, ketua kelas) -->
        <?php if (in_array($role, ['Bendahara', 'Walikelas', 'Ketua Kelas'])): ?>
            <li class="<?= active('laporan_kas.php') ?>">
                <a href="../../roles/<?= $role ?>/laporan_kas.php">
                    <img src="../../assets/img/laporan.png" alt="Laporan Kas">
                    <span>Laporan Kas</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</div>
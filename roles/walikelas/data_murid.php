<?php
session_start();
include '../../config/connect.php';

// Redirect jika belum login
if (!isset($_SESSION['login'])) {
    header('Location: ../../index.php');
    exit;
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// Hitung Ringkasan Data Murid
$totalMurid = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM murid"))['total'] ?? 0;
$muridAktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM murid WHERE status='Aktif'"))['total'] ?? 0;
$muridNonAktif = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM murid WHERE status='Non-Aktif'"))['total'] ?? 0;

// Ambil semua data murid
$query = mysqli_query($conn, "SELECT * FROM murid ORDER BY nama ASC");
$dataMurid = [];
while ($row = mysqli_fetch_assoc($query)) {
    $dataMurid[] = $row;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <title>Data Murid</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel='stylesheet' href='../../assets/css/sidebar.css'>
    <link rel='stylesheet' href='../../assets/css/walikelas/walikelas.css'>
    <link rel='stylesheet' href='../../assets/css/walikelas/data_murid.css'>
</head>
<body>

    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="fw-bold">Data Murid</h3>
            <h6 class="text-secondary"><?= htmlspecialchars($nama) ?> | <?= htmlspecialchars($role) ?></h6>
        </header>

        <div class="status-grid fade-in">
            <div class="status-card info fade-in">
                <div class="status-text">
                    <p>Total Semua Murid</p>
                    <h2><?= $totalMurid ?></h2>
                    <span>Siswa Terdaftar</span>
                </div>
                <div class="status-icon">
                    <img src="../../assets/img/person.png" width="24" onerror="this.src='https://cdn-icons-png.flaticon.com/512/3135/3135715.png'">
                </div>
            </div>

            <div class="status-card success fade-in">
                <div class="status-text">
                    <p>Murid Aktif</p>
                    <h2><?= $muridAktif ?></h2>
                    <span>Status Aktif</span>
                </div>
                <div class="status-icon">
                    <img src="../../assets/img/check.png" width="24">
                </div>
            </div>

            <div class="status-card danger fade-in">
                <div class="status-text">
                    <p>Murid Non-Aktif</p>
                    <h2><?= $muridNonAktif ?></h2>
                    <span>Status Non-Aktif</span>
                </div>
                <div class="status-icon">
                    <img src="../../assets/img/uncheck.png" width="24">
                </div>
            </div>
        </div>

        <div class="filter-bar fade-in">
            <input type="text" id="searchInput" class="form-control search" 
                   placeholder="Cari Nama, NISN, atau Status..." 
                   style="width: 100%;" autocomplete="off">
            </div>

        <div class="table-card fade-in">
            <table class="table-status">
                <thead>
                    <tr>
                        <th style="width:60px">No</th>
                        <th>Nama Murid</th>
                        <th style="width:160px">NISN</th>
                        <th style="width:180px">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataMurid)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">Tidak ada data murid.</td>
                        </tr>
                    <?php else: ?>
                        <?php $no = 1; foreach ($dataMurid as $row): ?>
                            <tr data-search="<?= strtolower(htmlspecialchars($row['nama'])) ?> <?= htmlspecialchars($row['nisn']) ?> <?= strtolower($row['status']) ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['nisn']) ?></td>
                                <td>
                                    <?php if ($row['status'] === 'Aktif'): ?>
                                        <span class="status-badge success">Aktif</span>
                                    <?php else: ?>
                                        <span class="status-badge danger">Non-Aktif</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr id="noResult" style="display:none">
                            <td colspan="4">
                                <div class="text-center text-secondary py-4">
                                    <i class="opacity-50">Tidak ada hasil yang cocok.</i>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        const searchInput = document.getElementById('searchInput');

        if (searchInput) {
            searchInput.addEventListener('input', function () {
                const keyword = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('.table-status tbody tr[data-search]');
                let visibleCount = 0;

                rows.forEach(row => {
                    const match = row.dataset.search.includes(keyword);
                    row.style.display = match ? '' : 'none';
                    if (match) visibleCount++;
                });

                const noResult = document.getElementById('noResult');
                if (noResult) noResult.style.display = visibleCount === 0 ? '' : 'none';
            });
        }
    </script>
</body>
</html>
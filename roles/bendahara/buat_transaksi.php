<?php
session_start();
include '../../config/connect.php';
if (!isset($_SESSION['login'])) {
    header('Location: ../../index.php');
    exit;
}

$error = '';

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];
$nisnLogin = $_SESSION['nisn'] ?? '';

// Convert Bulan
$bulanMap = [
    'January' => 'Januari',
    'February' => 'Februari',
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];

// Tentukan tab yang aktif
$tab = isset($_GET['pengeluaran']) ? 'pengeluaran' : 'pemasukan';

// Simpan pemasukan per minggu
if (isset($_POST['simpan_masuk'])) {
    $nisn = $_POST['nisn'];
    $tahun = $_POST['tahun'];
    $bulan = $_POST['bulan'];
    $ket = $_POST['keterangan'];

    if (isset($_POST['minggu'])) {
        foreach ($_POST['minggu'] as $m) {
            mysqli_query($conn, "
                INSERT INTO transaksi 
                (id_transaksi, nisn, tanggal, tahun, bulan, minggu, jenis, jumlah, keterangan)
                VALUES 
                (NULL, '$nisn', NOW(), '$tahun', '$bulan', '$m', 'Masuk', 5000, '$ket')
            ");
        }
    }

    header("Location: buat_transaksi.php?pemasukan");
    exit;
}

// Simpan pemasukan per bulan (hanya insert minggu yang belum dibayar)
if (isset($_POST['simpan_masuk_bulan'])) {
    $nisn  = $_POST['nisn_bulan'];
    $tahun = $_POST['tahun_bulan'];
    $ket   = $_POST['keterangan_bulan'] ?? '';

    if (isset($_POST['bulan_dipilih']) && is_array($_POST['bulan_dipilih'])) {
        foreach ($_POST['bulan_dipilih'] as $bulan) {
            // Cek minggu mana yang sudah dibayar di bulan ini
            $qCek = mysqli_query($conn, "
                SELECT minggu FROM transaksi
                WHERE nisn='$nisn' AND tahun='$tahun' AND bulan='$bulan' AND jenis='Masuk'
            ");
            $sudahBayar = [];
            while ($r = mysqli_fetch_assoc($qCek)) {
                $sudahBayar[] = (int) $r['minggu'];
            }

            // Insert hanya minggu yang belum dibayar
            for ($m = 1; $m <= 4; $m++) {
                if (!in_array($m, $sudahBayar)) {
                    mysqli_query($conn, "
                        INSERT INTO transaksi
                        (id_transaksi, nisn, tanggal, tahun, bulan, minggu, jenis, jumlah, keterangan)
                        VALUES
                        (NULL, '$nisn', NOW(), '$tahun', '$bulan', '$m', 'Masuk', 5000, '$ket')
                    ");
                }
            }
        }
    }

    header("Location: buat_transaksi.php?pemasukan");
    exit;
}

// Simpan pengeluaran
if (isset($_POST['simpan_keluar'])) {

    $tanggal = $_POST['tanggal'] ?? '';
    $jumlah = (int) ($_POST['jumlah'] ?? 0);
    $kategori = $_POST['kategori'] ?? '';
    $dokumentasiFile = null;

    if (!empty($_FILES['dokumentasi']['name'])) {

        $folder = "../../uploads/pengeluaran/";
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        $ext = pathinfo($_FILES['dokumentasi']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];

        if (!in_array(strtolower($ext), $allowed)) {
            $error = "Format file tidak didukung.";
        } else {

            $namaFile = 'DOC_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $path = $folder . $namaFile;

            if (!move_uploaded_file($_FILES['dokumentasi']['tmp_name'], $path)) {
                $error = "Gagal mengupload bukti transaksi.";
            } else {
                $dokumentasiFile = $namaFile;
            }
        }
    }
    // Validasi jumlah kosong
    if ($jumlah <= 0) {
        $error = "Jumlah pengeluaran tidak boleh kosong atau bernilai 0.";
    } else {

        // Hitung saldo terbaru
        $qSaldo = mysqli_query($conn, "
            SELECT 
                SUM(CASE WHEN jenis='Masuk' THEN jumlah ELSE 0 END) -
                SUM(CASE WHEN jenis='Keluar' THEN jumlah ELSE 0 END) AS saldo
            FROM transaksi
        ");
        $saldo = (int) (mysqli_fetch_assoc($qSaldo)['saldo'] ?? 0);

        // Validasi saldo
        if ($jumlah > $saldo) {
            $error = "Saldo Kas tidak mencukupi.<br>
                      Saldo Kas saat ini: <b>Rp " . number_format($saldo, 0, ',', '.') . "</b>";
        } else {

            $tahun = date('Y', strtotime($tanggal));
            $bulanEn = date('F', strtotime($tanggal));
            $bulan = $bulanMap[$bulanEn];

            mysqli_query($conn, "
                INSERT INTO transaksi 
                (id_transaksi, nisn, tanggal, tahun, bulan, jenis, jumlah, keterangan, dokumentasi)
                VALUES 
                (
                    NULL,
                    '$nisnLogin',
                    '$tanggal',
                    '$tahun',
                    '$bulan',
                    'Keluar',
                    '$jumlah',
                    '$kategori',
                    '$dokumentasiFile'
                )
            ");

            header("Location: buat_transaksi.php?pengeluaran");
            exit;
        }
    }
}


// Format rupiah
function rupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

// Get total pemasukan
$qMasuk = mysqli_query($conn, "SELECT SUM(jumlah) total, COUNT(*) jml FROM transaksi WHERE jenis='Masuk'");
$dataMasuk = mysqli_fetch_assoc($qMasuk);
$totalMasuk = $dataMasuk['total'] ?? 0;
$jmlMasuk = $dataMasuk['jml'] ?? 0;

// Get total pengeluaran
$qKeluar = mysqli_query($conn, "SELECT SUM(jumlah) total, COUNT(*) jml FROM transaksi WHERE jenis='Keluar'");
$dataKeluar = mysqli_fetch_assoc($qKeluar);
$totalKeluar = $dataKeluar['total'] ?? 0;
$jmlKeluar = $dataKeluar['jml'] ?? 0;

// Get daftar murid aktif
$qMurid = mysqli_query($conn, "SELECT * FROM murid WHERE status='Aktif'");
$muridList = [];
while ($mt = mysqli_fetch_assoc($qMurid)) {
    $muridList[] = $mt;
}

// Get riwayat pemasukan terbaru
$riwayatMasuk = mysqli_query($conn, "
    SELECT 
        t.nisn,
        m.nama,
        t.tanggal,
        t.tahun,
        t.bulan,
        SUM(t.jumlah) as total_jumlah,
        GROUP_CONCAT(DISTINCT t.minggu ORDER BY t.minggu ASC) as minggu_list
    FROM transaksi t
    JOIN murid m ON t.nisn = m.nisn
    WHERE t.jenis='Masuk'
    GROUP BY t.nisn, t.tanggal, t.tahun, t.bulan
    ORDER BY MAX(t.id_transaksi) DESC
    LIMIT 4
");

// Get riwayat pengeluaran terbaru
$riwayatKeluar = mysqli_query($conn, "SELECT * FROM transaksi WHERE jenis='Keluar' ORDER BY id_transaksi DESC LIMIT 3");

// Format tanggal
function formatTanggal($tanggal)
{
    return date('d F Y H.i', strtotime($tanggal));
}

// Saldo bersih :
$saldoBersih = $totalMasuk - $totalKeluar;

?>


<!DOCTYPE html>
<html>

<head>
    <title>Buat Transaksi</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="/KASKITA/assets/css/sidebar.css">
    <link rel="stylesheet" href="/KASKITA/assets/css/bendahara/bendahara.css">
    <link rel="stylesheet" href="/KASKITA/assets/css/bendahara/buat_transaksi.css">


</head>

<body class="buat-transaksi-page">
    <?php include '../../reuse/sidebar.php'; ?>
    <?php include '../../reuse/logout_btn.php'; ?>

    <div class="main-content">
        <header class="page-header">
            <h3 class="fw-bold">Buat Transaksi</h3>
            <h6 class="text-secondary mb-4"><?= $nama ?> | <?= $role ?></h6>
        </header>

        <div class="tab-container mb-4">
            <a id="tabMasuk" href="?pemasukan" class="custom-tab <?= $tab == 'pemasukan' ? 'active-masuk' : '' ?>">
                Pemasukan
            </a>

            <a id="tabKeluar" href="?pengeluaran"
                class="custom-tab <?= $tab == 'pengeluaran' ? 'active-keluar' : '' ?>">
                Pengeluaran
            </a>
        </div>

        <!-- Pemasukan -->
        <div <?= $tab == 'pemasukan' ? '' : 'class="d-none masuk"' ?>>
            <div class="row g-4">
                <!-- Total Pemasukan -->
                <div class="col-md-6 fade-in">
                    <div class="dashboard-card gradient-hijau">
                        <h6>Total Pemasukan</h6>
                        <h2 class="fw-bold"><?= rupiah($totalMasuk); ?></h2>
                        <small><?= $jmlMasuk; ?> Transaksi</small>

                        <div class="icon-circle">
                            <img id="icon-up" src="../../assets/img/up.png">
                        </div>
                    </div>
                </div>

                <!-- Saldo Bersih -->
                <div class="col-md-6 fade-in">
                    <div class="dashboard-card gradient-biru">
                        <h6>Saldo Bersih</h6>
                        <h2 class="fw-bold"><?= rupiah($saldoBersih); ?></h2>
                        <small>Pemasukan - Pengeluaran</small>

                        <div class="icon-circle">
                            <img id="icon-balance" src="../../assets/img/cash.png">
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mt-4 align-items-stretch">

                <!-- Form -->
                <div class="col-md-7 fade-in">
                    <div class="form-card">
                        <h5 class="mb-3">Buat Transaksi Pemasukan</h5>

                        <!-- Tab Per Minggu / Per Bulan -->
                        <div class="tab-container mb-4">
                            <a id="tabMinggu" href="#" class="custom-tab active-masuk"
                               onclick="switchSubTab('minggu'); return false;">Bayar Per Minggu</a>
                            <a id="tabBulan" href="#" class="custom-tab"
                               onclick="switchSubTab('bulan'); return false;">Bayar Per Bulan</a>
                        </div>

                        <!-- Form Per Minggu -->
                        <div id="formWrapMinggu">
                        <form method="POST" id="formPemasukan">
                            <div id="mingguContainer"></div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Murid</label>
                                <select name="nisn" id="selectMurid" class="form-select" required>
                                    <option selected disabled value="">Pilih Murid</option>
                                    <?php foreach ($muridList as $m): ?>
                                        <option value="<?= $m['nisn'] ?>">
                                            <?= $m['nama'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Tahun</label>
                                    <input type="text" id="selectTahun" name="tahun" class="form-control bg-light" readonly
                                        placeholder="Tahun">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Bulan</label>
                                    <input type="text" name="bulan" id="selectBulan" class="form-control bg-light"
                                        placeholder="Bulan" readonly required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <button type="button" class="btn btn-secondary minggu-btn" data-minggu="<?= $i ?>"
                                        disabled>M - <?= $i ?></button>
                                <?php endfor; ?>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">Jumlah</label>
                                <input type="text" id="jumlah_view" class="form-control" readonly value="Rp 0">
                                <input type="hidden" id="jumlah" name="jumlah" value="0">
                            </div>

                            <div class="mb-3 fw-bold">
                                <label>Keterangan (Opsional)</label>
                                <textarea name="keterangan" class="form-control" rows="3"
                                    placeholder="Tambahkan keterangan..."></textarea>
                            </div>

                            <button type="submit" name="simpan_masuk" class="btn btn-gradient-masuk w-100 btn-simpan">
                                Simpan Pemasukan
                            </button>
                        </form>
                        </div>

                        <!-- Form Per Bulan -->
                        <div id="formWrapBulan" class="d-none">
                        <form method="POST" id="formPemasukanBulan">
                            <div id="bulanContainer"></div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Murid</label>
                                <select name="nisn_bulan" id="selectMuridBulan" class="form-select" required>
                                    <option selected disabled value="">Pilih Murid</option>
                                    <?php foreach ($muridList as $m): ?>
                                        <option value="<?= $m['nisn'] ?>">
                                            <?= $m['nama'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">Tahun</label>
                                <input type="text" id="selectTahunBulan" name="tahun_bulan"
                                    class="form-control bg-light" readonly placeholder="Tahun">
                            </div>

                            <!-- Tombol multi-select bulan -->
                            <div class="mb-3">
                                <label class="fw-bold d-block mb-2">Bulan</label>
                                <?php
                                $daftarBulanList = ['Januari','Februari','Maret','April','Mei','Juni',
                                                    'Juli','Agustus','September','Oktober','November','Desember'];
                                foreach ($daftarBulanList as $bl): ?>
                                    <button type="button" class="btn btn-secondary bulan-btn"
                                            data-bulan="<?= $bl ?>" disabled>
                                        <?= $bl ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">Jumlah</label>
                                <input type="text" id="jumlah_view_bulan" class="form-control" readonly value="Rp 0">
                                <input type="hidden" id="jumlah_bulan" name="jumlah_bulan" value="0">
                            </div>

                            <div class="mb-3 fw-bold">
                                <label>Keterangan (Opsional)</label>
                                <textarea name="keterangan_bulan" class="form-control" rows="3"
                                    placeholder="Tambahkan keterangan..."></textarea>
                            </div>

                            <button type="submit" name="simpan_masuk_bulan"
                                class="btn btn-gradient-masuk w-100 btn-simpan">
                                Simpan Pemasukan
                            </button>
                        </form>
                        </div>

                    </div>
                </div>

                <!-- Riwayat -->
                <div class="col-md-5 fade-in">
                    <div class="riwayat-card riwayat-masuk">
                        <h5 class="mb-4">Riwayat Pemasukan</h5>
                        <?php if (mysqli_num_rows($riwayatMasuk) > 0): ?>

                            <?php while ($r = mysqli_fetch_assoc($riwayatMasuk)): ?>
                                <div class="riwayat-item">
                                    <div class="riwayat-left">
                                        <div class="riwayat-icon">
                                            <img src="../../assets/img/up.png">
                                        </div>
                                        <div class="riwayat-info">
                                            <div class="riwayat-title">Pembayaran Kas</div>
                                            <div class="riwayat-name">
                                                <?= $r['nama']; ?>
                                            </div>
                                            <div class="riwayat-meta">
                                                <span class="riwayat-date">
                                                    <?= formatTanggal($r['tanggal']); ?>
                                                </span>

                                                <span class="badge-minggu">
                                                    Untuk <?= $r['bulan']; ?>         <?= $r['tahun']; ?> • Minggu
                                                    <?= $r['minggu_list']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="riwayat-amount">
                                        + <?= rupiah($r['total_jumlah']); ?>
                                    </div>
                                </div>


                            <?php endwhile; ?>
                            <div class="text-center mt-3 text-secondary">
                                <a href="" class="activity-detail-link">
                                    Lihat Detail
                                </a>
                            </div>

                        <?php else: ?>

                            <div class="text-center text-secondary py-4">
                                <i class="opacity-50">Belum ada riwayat.</i>
                            </div>

                        <?php endif; ?>



                    </div>
                </div>

            </div>

        </div>

        <!-- Pengeluaran -->
        <div <?= $tab == 'pengeluaran' ? '' : 'class="d-none"' ?>>
            <div class="row g-4 mb-4">
                <!-- Total Pengeluaran -->
                <div class="col-md-6 fade-in">
                    <div class="dashboard-card gradient-merah">
                        <h6>Total Pengeluaran</h6>
                        <h2 class="fw-bold"><?= rupiah($totalKeluar); ?></h2>
                        <small><?= $jmlKeluar; ?> Transaksi</small>

                        <div class="icon-circle">
                            <img id="icon-down" src="../../assets/img/down.png">
                        </div>
                    </div>
                </div>

                <!-- Saldo Bersih -->
                <div class="col-md-6 fade-in">
                    <div class="dashboard-card gradient-biru">
                        <h6>Saldo Bersih</h6>
                        <h2 class="fw-bold"><?= rupiah($saldoBersih); ?></h2>
                        <small>Saldo Kas Tersedia</small>

                        <div class="icon-circle">
                            <img id="icon-balance" src="../../assets/img/cash.png">
                        </div>
                    </div>
                </div>
            </div>

            <div class="row align-items-stretch">

                <!-- Pengeluaran -->
                <div class="col-md-7 fade-in">
                    <div class="form-card">
                        <h5 class="mb-4">Buat Transaksi Pengeluaran</h5>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="fw-bold" hidden>Bendahara</label>
                                <input type="text" class="form-control" value="<?= $nama ?>" readonly hidden>
                                <input type="hidden" name="bendahara" value="<?= $nama ?>">
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">Tanggal</label>
                                <input type="datetime-local" name="tanggal" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">Kategori Pengeluaran</label>
                                <select name="kategori" class="form-select" required>
                                    <option value="">Pilih Kategori</option>
                                    <option value="ATK">ATK</option>
                                    <option value="Kegiatan Sekolah">Kegiatan Sekolah</option>
                                    <option value="Perbaikan Fasilitas">Perbaikan Fasilitas</option>
                                    <option value="Konsumsi">Konsumsi</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">Jumlah</label>
                                <input type="text" id="jumlahRupiah" class="form-control" value="Rp " autocomplete="off"
                                    required>
                                <input type="hidden" name="jumlah" id="jumlahAsli">
                            </div>

                            <div class="mb-3">
                                <label class="fw-bold">
                                    Bukti Transaksi
                                    <small class="text-muted">(Struk / Nota / Lainnya)</small>
                                </label>
                                <input type="file" name="dokumentasi" class="form-control" accept="image/*,.pdf"
                                    required>
                            </div>

                            <button name="simpan_keluar" class="btn btn-gradient-keluar w-100 btn-keluar">
                                Simpan Pengeluaran
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Riwayat -->
                <div class="col-md-5 fade-in">
                    <div class="riwayat-card riwayat-keluar">
                        <h5 class="mb-4">Riwayat Pengeluaran</h5>

                        <?php if (mysqli_num_rows($riwayatKeluar) > 0): ?>
                            <?php while ($r = mysqli_fetch_assoc($riwayatKeluar)): ?>
                                <div class="riwayat-item">
                                    <div class="riwayat-left">
                                        <div class="riwayat-icon">
                                            <img src="../../assets/img/down.png">
                                        </div>
                                        <div>
                                            <div class="riwayat-title">
                                                <?= $r['keterangan']; ?>
                                            </div>

                                            <div class="riwayat-date">
                                                <?= formatTanggal($r['tanggal']); ?>
                                            </div>

                                            <?php if (!empty($r['dokumentasi'])): ?>
                                                <a href="../../uploads/pengeluaran/<?= $r['dokumentasi']; ?>" target="_blank"
                                                    class="lihat-dokumentasi">
                                                    Bukti Transaksi
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="riwayat-amount text-danger">
                                        - <?= rupiah($r['jumlah']); ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <div class="text-center mt-3 text-secondary">
                                <a href="" class="activity-detail-link">
                                    Lihat Detail
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-secondary py-4">
                                <i class="opacity-50">Belum ada riwayat.</i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>


    </div>

    <script>
        // Harga kas per minggu
        let harga = 5000;
        let mingguDipilih = [];

        // Event listener untuk tombol minggu
        document.querySelectorAll('.minggu-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                let minggu = parseInt(this.dataset.minggu);

                // Jika minggu sudah dipilih, batalkan pilihan minggu ini dan setelah
                if (this.classList.contains('btn-success')) {
                    mingguButtons.forEach(btn2 => {
                        let m = parseInt(btn2.dataset.minggu);
                        if (m >= minggu && mingguDipilih.includes(m.toString())) {
                            btn2.classList.add('animate-unselect');
                            setTimeout(() => {
                                btn2.classList.remove('btn-success', 'animate-unselect');
                                btn2.classList.add('btn-danger');
                                mingguDipilih = mingguDipilih.filter(x => x != m.toString());
                            }, 280);
                            setTimeout(() => btn2.classList.remove('animate-unselect'), 200);
                            mingguDipilih = mingguDipilih.filter(x => x != m.toString());
                        }
                    });
                    updateJumlah();
                    return;
                }

                // Pilih minggu dari 1 sampai minggu yang diklik
                for (let i = 1; i <= minggu; i++) {
                    let btnTarget = document.querySelector(`.minggu-btn[data-minggu="${i}"]`);
                    if (btnTarget.disabled && btnTarget.classList.contains('btn-success')) continue;
                    if (!mingguDipilih.includes(i.toString())) {
                        btnTarget.classList.remove('btn-danger');
                        btnTarget.classList.add('btn-success', 'animate-select');
                        setTimeout(() => btnTarget.classList.remove('animate-select'), 250);
                        mingguDipilih.push(i.toString());
                    }
                }
                updateJumlah();
            });
        });

        // Update total jumlah dan buat hidden input untuk minggu
        function updateJumlah() {
            let total = mingguDipilih.length * harga;
            document.getElementById('jumlah').value = total;
            document.getElementById('jumlah_view').value = formatRupiah(total);

            let container = document.getElementById('mingguContainer');
            container.innerHTML = '';
            mingguDipilih.forEach(m => {
                container.innerHTML += `<input type="hidden" name="minggu[]" value="${m}">`;
            });
        }

        // Format angka ke rupiah
        function formatRupiah(angka) {
            let number_string = angka.toString();
            let sisa = number_string.length % 3;
            let rupiah = number_string.substr(0, sisa);
            let ribuan = number_string.substr(sisa).match(/\d{3}/g);
            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            return 'Rp ' + rupiah;
        }



        // Get elemen form select
        const selectMurid = document.getElementById('selectMurid');
        const selectTahun = document.getElementById('selectTahun');
        const selectBulan = document.getElementById('selectBulan');
        const mingguButtons = document.querySelectorAll('.minggu-btn');

        // Event saat murid dipilih — auto-set tahun berdasarkan riwayat pembayaran
        selectMurid.addEventListener('change', async function () {
            selectTahun.value = "";
            selectBulan.value = "";
            resetMinggu();

            if (this.value === "") return;

            // Fetch tahun aktif untuk murid ini (tahun pertama yang belum lunas)
            const res = await fetch("get_next_tahun.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `nisn=${this.value}`
            });
            const data = await res.json();

            // Set tahun sebagai teks readonly
            selectTahun.value = data.next_tahun;

            // Langsung cek bulan & minggu berdasarkan tahun yang sudah di-set
            cekMingguDibayar();
        });

        // Event saat bulan dipilih
        selectBulan.addEventListener('change', function () {
            if (this.value !== "") {
                mingguButtons.forEach(btn => btn.disabled = false);
                cekMingguDibayar();
            } else {
                resetMinggu();
            }
        });

        // Reset status minggu
        function resetMinggu() {
            mingguButtons.forEach(btn => {
                btn.classList.remove('btn-success', 'btn-danger');
                btn.classList.add('btn-secondary');
                btn.disabled = true;
            });
            mingguDipilih = [];
            updateJumlah();
        }

        // Daftar urutan bulan untuk konversi atau pengecekan
        const daftarBulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];


        // Cek minggu mana yang sudah dibayar
        async function cekMingguDibayar() {
            let nisn = selectMurid.value;
            let tahun = selectTahun.value;

            if (!nisn || !tahun) {
                selectBulan.value = "";
                resetMinggu();
                return;
            }

            let response = await fetch("get_next_bulan.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `nisn=${nisn}&tahun=${tahun}`
            });

            let resData = await response.json();

            if (resData.next_bulan) {
                selectBulan.value = resData.next_bulan;

                // Aktifkan tombol minggu
                mingguButtons.forEach(btn => {
                    btn.disabled = false;
                    let minggu = parseInt(btn.dataset.minggu);

                    // Tandai merah untuk minggu yang belum dibayar, hijau untuk yang sudah (di bulan tersebut)
                    if (resData.minggu_lunas.includes(minggu)) {
                        btn.classList.remove('btn-secondary', 'btn-danger');
                        btn.classList.add('btn-success');
                        btn.disabled = true;
                    } else {
                        btn.classList.remove('btn-secondary', 'btn-success');
                        btn.classList.add('btn-danger');
                        btn.disabled = false;
                    }
                });
            }
        }

        // Validasi form pemasukan
        const formPemasukan = document.getElementById('formPemasukan');
        if (formPemasukan) {
            formPemasukan.addEventListener('submit', function (e) {
                if (mingguDipilih.length === 0) {
                    e.preventDefault();
                    alert("Minggu belum dipilih!");
                }
            });
        }

        // Input formatter untuk pengeluaran (rupiah)
        const rupiahInput = document.getElementById('jumlahRupiah');
        const asliInput = document.getElementById('jumlahAsli');

        if (!rupiahInput.value.startsWith('Rp')) {
            rupiahInput.value = 'Rp ';
        }

        rupiahInput.addEventListener('input', function () {
            let angka = this.value.replace(/[^0-9]/g, '');
            asliInput.value = angka;
            this.value = 'Rp ' + (angka ? angka.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '');
        });

        rupiahInput.addEventListener('keydown', function (e) {
            if (this.selectionStart <= 3 && (e.key === 'Backspace' || e.key === 'Delete')) {
                e.preventDefault();
            }
        });

        rupiahInput.addEventListener('blur', function () {
            if (this.value.trim() === 'Rp') {
                this.value = 'Rp ';
                asliInput.value = '';
            }
        });

        // ===== Sub-tab Per Minggu / Per Bulan =====

        // Switch antara form per minggu dan per bulan + reset input
        function switchSubTab(mode) {
            const tabMinggu  = document.getElementById('tabMinggu');
            const tabBulan   = document.getElementById('tabBulan');
            const wrapMinggu = document.getElementById('formWrapMinggu');
            const wrapBulan  = document.getElementById('formWrapBulan');

            if (mode === 'minggu') {
                tabMinggu.classList.add('active-masuk');
                tabBulan.classList.remove('active-masuk');
                wrapMinggu.classList.remove('d-none');
                wrapBulan.classList.add('d-none');
                // Reset form per bulan
                selectMuridBulan.value = '';
                selectTahunBulan.value = '';
                resetBulanBtn();
            } else {
                tabBulan.classList.add('active-masuk');
                tabMinggu.classList.remove('active-masuk');
                wrapBulan.classList.remove('d-none');
                wrapMinggu.classList.add('d-none');
                // Reset form per minggu
                selectMurid.value  = '';
                selectTahun.value  = '';
                selectBulan.value  = '';
                resetMinggu();
            }
        }

        // ===== Form Per Bulan =====

        const selectMuridBulan  = document.getElementById('selectMuridBulan');
        const selectTahunBulan  = document.getElementById('selectTahunBulan');
        const bulanButtons      = document.querySelectorAll('.bulan-btn');
        let bulanDipilih        = [];
        const hargaPerBulan     = 20000;

        // Saat murid dipilih di form per bulan — auto-set tahun
        selectMuridBulan.addEventListener('change', async function () {
            selectTahunBulan.value = '';
            resetBulanBtn();

            if (!this.value) return;

            // Fetch tahun aktif
            const res  = await fetch("get_next_tahun.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `nisn=${this.value}`
            });
            const data = await res.json();
            selectTahunBulan.value = data.next_tahun;

            // Aktifkan tombol bulan & tandai yang sudah lunas
            await cekBulanDibayar();
        });

        // Cek bulan mana yang sudah lunas (4 minggu terbayar) untuk murid & tahun ini
        let mingguPerBulanData = {}; // simpan data minggu per bulan untuk kalkulasi jumlah

        async function cekBulanDibayar() {
            const nisn  = selectMuridBulan.value;
            const tahun = selectTahunBulan.value;
            if (!nisn || !tahun) return;

            const res  = await fetch("cek_minggu.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `nisn=${nisn}&tahun=${tahun}&mode=bulan`
            });

            const result = await res.json();
            const lunas  = result.lunas;           // bulan yang sudah 4 minggu
            mingguPerBulanData = result.minggu_per_bulan; // { Januari: 2, Maret: 1, ... }

            bulanButtons.forEach(btn => {
                const b = btn.dataset.bulan;
                btn.disabled = false;
                if (lunas.includes(b)) {
                    // Sudah lunas — hijau, tidak bisa dipilih
                    btn.classList.remove('btn-secondary', 'btn-danger');
                    btn.classList.add('btn-success');
                    btn.disabled = true;
                } else {
                    // Belum lunas — merah, bisa dipilih
                    btn.classList.remove('btn-secondary', 'btn-success');
                    btn.classList.add('btn-danger');
                }
            });
        }

        // Klik tombol bulan — multi-select berurutan dari kiri
        // Klik tombol bulan — pilih berurutan dari bulan pertama yang belum lunas sampai bulan diklik
        bulanButtons.forEach(btn => {
            btn.addEventListener('click', function () {
                if (this.disabled) return;

                const bulanList = Array.from(bulanButtons);
                const idxKlik   = bulanList.indexOf(this);

                // Jika bulan ini sudah dipilih → batalkan dari bulan ini ke kanan
                if (this.classList.contains('active-masuk')) {
                    for (let i = idxKlik; i < bulanList.length; i++) {
                        const b = bulanList[i];
                        if (b.classList.contains('active-masuk')) {
                            b.classList.remove('active-masuk');
                            b.classList.add('btn-danger');
                            bulanDipilih = bulanDipilih.filter(x => x !== b.dataset.bulan);
                        }
                    }
                } else {
                    // Pilih dari bulan pertama yang belum dipilih & belum lunas, sampai bulan diklik
                    for (let i = 0; i <= idxKlik; i++) {
                        const b = bulanList[i];
                        // Lewati yang sudah lunas (btn-success disabled)
                        if (b.disabled) continue;
                        if (!bulanDipilih.includes(b.dataset.bulan)) {
                            b.classList.remove('btn-danger', 'btn-secondary');
                            b.classList.add('active-masuk');
                            bulanDipilih.push(b.dataset.bulan);
                        }
                    }
                }

                updateJumlahBulan();
            });
        });

        // Update total jumlah — hitung hanya minggu yang belum dibayar per bulan
        function updateJumlahBulan() {
            let total = 0;
            bulanDipilih.forEach(b => {
                const sudah = mingguPerBulanData[b] || 0; // minggu yang sudah dibayar
                const sisa  = 4 - sudah;                  // minggu yang belum dibayar
                total += sisa * 5000;
            });

            document.getElementById('jumlah_bulan').value = total;
            document.getElementById('jumlah_view_bulan').value = formatRupiah(total);

            const container = document.getElementById('bulanContainer');
            container.innerHTML = '';
            bulanDipilih.forEach(b => {
                container.innerHTML += `<input type="hidden" name="bulan_dipilih[]" value="${b}">`;
            });
        }

        // Reset semua tombol bulan ke state awal
        function resetBulanBtn() {
            bulanButtons.forEach(btn => {
                btn.classList.remove('btn-success', 'btn-danger', 'active-masuk');
                btn.classList.add('btn-secondary');
                btn.disabled = true;
            });
            bulanDipilih = [];
            updateJumlahBulan();
        }

        // Validasi form per bulan
        document.getElementById('formPemasukanBulan').addEventListener('submit', function (e) {
            if (bulanDipilih.length === 0) {
                e.preventDefault();
                alert("Bulan belum dipilih!");
            }
        });

    </script>
    <!-- Error Modal -->
    <?php if ($error != ''): ?>
        <div class="modal fade" id="errorModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2">
                            <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            Transaksi Gagal
                        </h5>
                    </div>
                    <div class="modal-body">
                        <?= $error ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                            OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            window.addEventListener('load', function () {
                var modal = new bootstrap.Modal(document.getElementById('errorModal'));
                modal.show();
            });
        </script>
    <?php endif; ?>

</body>

</html>
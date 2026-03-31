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

// Simpan pemasukan (cicilan kas per minggu)
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
            $error = "Format dokumentasi tidak didukung.";
        } else {

            $namaFile = 'DOC_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $path = $folder . $namaFile;

            if (!move_uploaded_file($_FILES['dokumentasi']['tmp_name'], $path)) {
                $error = "Gagal mengupload dokumentasi.";
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
        GROUP_CONCAT(t.minggu ORDER BY t.minggu ASC) as minggu_list
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
                        <h5 class="mb-4">Buat Transaksi Pemasukan</h5>
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
                                    <select name="tahun" id="selectTahun" class="form-select" disabled required>
                                        <option value="" selected disabled>Pilih Tahun</option>
                                        <option>2025</option>
                                        <option>2026</option>
                                        <option>2027</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="fw-bold">Bulan</label>
                                    <select name="bulan" id="selectBulan" class="form-select" disabled required>
                                        <option value="" selected disabled>Pilih Bulan</option>
                                        <option>Januari</option>
                                        <option>Februari</option>
                                        <option>Maret</option>
                                        <option>April</option>
                                        <option>Mei</option>
                                        <option>Juni</option>
                                        <option>Juli</option>
                                        <option>Agustus</option>
                                        <option>September</option>
                                        <option>Oktober</option>
                                        <option>November</option>
                                        <option>Desember</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php for ($i = 1; $i <= 4; $i++): ?>
                                    <button type="button" class="btn btn-secondary minggu-btn" data-minggu="<?= $i ?>"
                                        disabled>
                                        M -
                                        <?= $i ?>
                                    </button>
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
                                <label class="fw-bold">Bendahara</label>
                                <input type="text" class="form-control" value="<?= $nama ?>" readonly>
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
                                    Dokumentasi
                                    <small class="text-muted">(Struk / Nota / Foto Kegiatan / Lainnya)</small>
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
                                                    Lihat Dokumentasi
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

        // Event saat murid dipilih
        selectMurid.addEventListener('change', function () {
            selectTahun.value = "";
            selectBulan.value = "";
            selectTahun.disabled = (this.value === "");
            selectBulan.disabled = true;
            resetMinggu();
        });

        // Event saat tahun dipilih
        selectTahun.addEventListener('change', function () {
            selectBulan.value = "";
            selectBulan.disabled = (this.value === "");
            mingguButtons.forEach(btn => {
                btn.classList.remove('btn-success', 'btn-danger');
                btn.classList.add('btn-secondary');
                btn.disabled = true;
            });
            mingguDipilih = [];
            updateJumlah();
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

        // Check minggu mana yang sudah dibayar
        async function cekMingguDibayar() {
            let nisn = selectMurid.value;
            let tahun = selectTahun.value;
            let bulan = selectBulan.value;
            if (!nisn || !tahun || !bulan) return;

            let response = await fetch("cek_minggu.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `nisn=${nisn}&tahun=${tahun}&bulan=${bulan}`
            });

            let data = await response.json();
            mingguButtons.forEach(btn => {
                btn.classList.remove('btn-success');
                btn.classList.add('btn-danger');
                btn.disabled = false;
                let minggu = btn.dataset.minggu;
                if (data.includes(parseInt(minggu))) {
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-success');
                    btn.disabled = true;
                }
            });
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
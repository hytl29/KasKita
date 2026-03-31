<?php
session_start();
include '../config/connect.php';

$error = '';

if (isset($_POST['login'])) {
    $u = trim($_POST['username']);
    $p = trim($_POST['password']);

    // Validasi input kosong dan format NISN/NIP
    if ($u === '' || $p === '') {
        $error = 'NISN / NIP dan Password tidak boleh kosong!';
    } elseif (!preg_match('/^[0-9]{10}$|^[0-9]{18}$/', $u)) {
        $error = 'NISN / NIP harus 10 atau 18 digit angka!';
    }

    // Login Murid (NISN 10 digit)
    if (preg_match('/^[0-9]{10}$/', $u)) {

        $query = mysqli_query($conn, "SELECT * FROM murid WHERE nisn='$u'");
        $data = mysqli_fetch_assoc($query);

        if (!$data) {
            $error = 'NISN / NIP atau Password salah!';
        } elseif ($data['password'] != $p) {
            $error = 'NISN / NIP atau Password salah!';
        } else {
            $_SESSION['login'] = true;
            $_SESSION['nisn'] = $data['nisn'];
            $_SESSION['nama'] = $data['nama'];
            $_SESSION['role'] = $data['role'];

            header("Location: ../reuse/loading.php");
            exit;

        }
    }
    // Login Wali Kelas (NIP 18 digit)
    elseif (preg_match('/^[0-9]{18}$/', $u)) {

        $query = mysqli_query($conn, "SELECT * FROM walikelas WHERE nip='$u'");
        $data = mysqli_fetch_assoc($query);

        if (!$data) {
            $error = 'Akun tidak ditemukan!';
        } elseif ($data['password'] != $p) {
            $error = 'Password salah!';
        } else {
            $_SESSION['login'] = true;
            $_SESSION['nip'] = $data['nip'];
            $_SESSION['nama'] = $data['nama'];
            $_SESSION['role'] = "Walikelas";

            header("Location: ../reuse/loading.php");
            exit;
        }
    }
}
?>


<head>
    <meta charset="UTF-8">
    <title>Login Sistem Kas Kelas</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <link rel="stylesheet" href="../assets/css/login.css">
</head>

<body>
    <?php if ($error == ''): ?>
        <div id="loader">
            <div class="spinner-border text-primary"></div>
        </div>
    <?php endif; ?>
    
    <?php if ($error != ''): ?>
        <div class="modal fade" id="errorModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content shadow-lg">
                    <div class="modal-header">
                        <h5 class="modal-title d-flex align-items-center gap-2">
                            <i class="bi bi-x-circle-fill text-danger fs-4"></i>
                            Login Gagal
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



    <div class="login-wrapper">
        <div class="login-card">

            <div class="text-center mb-3">
                <div class="icon-circle">
                    <img src="../assets/img/wallet.png">
                </div>
                <h5 class="mt-3 fw-bold">Sistem Kas Kelas</h5>
                <p class="text-muted small">Login untuk mengakses Sistem Kas Kelas</p>
            </div>

            <form action="../auth/login.php" method="POST">

                <div class="mb-3">
                    <label>NISN / NIP</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan NISN / NIP Anda">
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control"
                            placeholder="Masukkan Password Anda">

                        <span class="input-group-text toggle-password-icon" onclick="togglePassword()">
                            <i class="bi bi-eye-slash" id="eyeIcon"></i>
                        </span>
                    </div>
                </div>

                <button name="login" class="btn btn-login w-100">Login</button>
            </form>

        </div>
    </div>
</body>

<script>
    function togglePassword() {
        const password = document.getElementById("password");
        const eyeIcon = document.getElementById("eyeIcon");

        if (password.type === "password") {
            password.type = "text";
            eyeIcon.classList.remove("bi-eye-slash");
            eyeIcon.classList.add("bi-eye");
        } else {
            password.type = "password";
            eyeIcon.classList.remove("bi-eye");
            eyeIcon.classList.add("bi-eye-slash");
        }
    }
</script>

<?php if ($error == ''): ?>
<script>
    window.addEventListener("load", function () {
        setTimeout(() => {
            const loader = document.getElementById("loader");
            if (loader) loader.style.display = "none";
        }, 1000);
    });
</script>
<?php endif; ?>

</html>
<?php
session_start();

// Hapus semua data session dan redirect ke login
session_destroy();
header('Location: ../auth/login.php');
exit;

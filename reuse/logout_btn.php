<?php
// Komponen tombol logout — include di setiap halaman
?>
<div class="logout-wrapper">
    <a href="../../auth/logout.php" class="logout-btn" title="Logout">
        <img src="../../assets/img/logout.png" alt="logout">
        Keluar
    </a>
</div>

<script>
    // Turunkan opacity logout saat scroll, kembalikan saat di atas
    window.addEventListener('scroll', function () {
        const wrapper = document.querySelector('.logout-wrapper');
        if (!wrapper) return;
        wrapper.classList.toggle('scrolled', window.scrollY > 10);
    });
</script>

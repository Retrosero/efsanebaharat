<!-- Gün Sonu Raporu -->
<?php if (yetkiKontrol('gunsonu', 'goruntuleme')): ?>
<li class="nav-item">
    <a class="nav-link <?php echo ($current_page == 'gunsonu.php') ? 'active' : ''; ?>" href="gunsonu.php">
        <i class="fas fa-chart-line"></i>
        <span>Gün Sonu Raporu</span>
    </a>
</li>
<?php endif; ?>

<!-- Raporlar -->
<?php if (yetkiKontrol('raporlar', 'goruntuleme')): ?>
<li class="nav-item">
    <a class="nav-link <?php echo ($current_page == 'raporlar.php') ? 'active' : ''; ?>" href="raporlar.php">
        <i class="fas fa-chart-bar"></i>
        <span>Raporlar</span>
    </a>
</li>
<?php endif; ?>

<!-- Ayarlar --> 
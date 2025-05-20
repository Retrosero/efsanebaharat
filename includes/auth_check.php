<?php
// auth_check.php - Sadece temel oturum kontrolü için

// Oturum başlatma kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Kullanıcı giriş yapmış mı kontrol et
if (!isset($_SESSION['kullanici_id'])) {
    echo '<p class="text-center text-red-500">Bu sayfaya erişmek için giriş yapmanız gerekiyor.</p>';
    exit;
}
?> 
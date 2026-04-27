<?php
// includes/session.php
// Tekil ve tutarli oturum kontrolu: includes/auth.php ile ayni oturum yapisini kullanir.

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Giris yoksa once remember token ile dene
if (!isset($_SESSION['kullanici_id']) && !hatirlamaTokeniKontrol($pdo)) {
    header('Location: giris.php');
    exit();
}

// Son aktiviteyi guncelle
$_SESSION['son_aktivite'] = time();

// CSRF token olustur
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

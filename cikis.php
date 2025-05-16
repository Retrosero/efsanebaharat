<?php
// Gerekli dosyaları dahil et
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Kullanıcı çıkış fonksiyonunu çağır
kullaniciCikis();

// Giriş sayfasına yönlendir
header('Location: giris.php');
exit;
?> 
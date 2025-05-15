<?php
session_start();

// Tüm oturum verilerini sil
session_destroy();

// Giriş sayfasına yönlendir
header('Location: giris.php');
exit;
?> 
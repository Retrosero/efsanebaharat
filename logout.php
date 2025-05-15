<?php
// logout.php
require_once 'includes/auth.php';

// Session'ı başlat (eğer başlamadıysa)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Tüm session değişkenlerini temizle
$_SESSION = array();

// Session çerezini sil
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Remember me çerezini sil
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Session'ı yok et
session_destroy();

// Tarayıcı önbelleğini temizlemek için header'lar
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Giriş sayfasına yönlendir
header('Location: login.php');
exit; 
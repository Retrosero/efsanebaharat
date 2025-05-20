<?php
// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    // Eğer giriş sayfalarında değilsek, index sayfasına yönlendir
    $current_page = basename($_SERVER['PHP_SELF']);
    $allowed_pages = ['login.php', 'giris.php', 'index.php'];
    
    if (!in_array($current_page, $allowed_pages)) {
        header("Location: index.php");
        exit();
    }
}

// Oturum süresini kontrol et
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    // Son aktiviteden bu yana 1 saat geçmişse oturumu sonlandır
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Son aktivite zamanını güncelle
$_SESSION['last_activity'] = time();

// CSRF token kontrolü
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token doğrulaması başarısız.');
    }
}

// XSS koruması için çıktı tamponlamasını başlat
ob_start();

// Varsayılan karakter seti
header('Content-Type: text/html; charset=utf-8');

// Zaman dilimini ayarla
date_default_timezone_set('Europe/Istanbul');

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/php_error.log');

// Oturum güvenliği için ek önlemler
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

// IP bazlı oturum kontrolü
if (isset($_SESSION['ip_address'])) {
    if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
} else {
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'];
}

// Tarayıcı bazlı oturum kontrolü
if (isset($_SESSION['user_agent'])) {
    if ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
    }
} else {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
} 
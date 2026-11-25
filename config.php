<?php
// config.php
// Geriye dönük uyumluluk: eski sayfalar $db değişkenini kullanır. Tüm oturum/giriş
// kontrolleri includes/db.php -> includes/auth.php üzerinden yapılır.

require_once __DIR__ . '/includes/db.php';

if (!defined('DB_NAME')) {
    define('DB_NAME', getenv('DB_NAME') ?: 'efsanebaharat');
}

date_default_timezone_set('Europe/Istanbul');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

$db = $pdo;

// XSS koruma fonksiyonu
if (!function_exists('guvenlik')) {
    function guvenlik($data) {
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}

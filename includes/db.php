<?php
// includes/db.php
// Tüm sayfaların ortak giriş noktası: güvenlik başlıkları + veritabanı + oturum/giriş kontrolü + CSRF kontrolü.

require_once __DIR__ . '/guvenlik.php';

// Hataları kullanıcıya gösterme, logla (APP_DEBUG=1 ile açılabilir)
if (!getenv('APP_DEBUG')) {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

if (!isset($pdo)) {
    $host     = getenv('DB_HOST') ?: 'localhost';
    $dbname   = getenv('DB_NAME') ?: 'efsanebaharat';
    $user     = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASS') ?: '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        error_log('Veritabanı bağlantı hatası: ' . $e->getMessage());
        http_response_code(500);
        die('Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyiniz.');
    }
}

guvenlikBasliklariGonder();

// Oturum başlatma + giriş zorunluluğu (giris.php / login.php hariç)
require_once __DIR__ . '/auth.php';

// header.php içermeyen uç noktalar için rol yetkisi
ucNoktaYetkiKontrol($pdo);

// Siteler arası istek sahteciliğine (CSRF) karşı kaynak kontrolü
csrfKontrol();

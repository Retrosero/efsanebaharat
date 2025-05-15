<?php
// config.php

// Hata raporlama
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session ayarları
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));

session_start();

// Auth dosyası şu anda geçici olarak kapalı - hata alıyorsanız bu dosyayı sunucuya yüklemeniz gerekir
// require_once 'includes/auth.php';

// Şu anda herhangi bir oturum kontrolü yapmıyoruz

/* Giriş gerektirmeyen sayfalar
$public_pages = [
    'login.php',
    'logout.php'
];

// Mevcut sayfa adını al
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Eğer mevcut sayfa giriş gerektirmeyen bir sayfa değilse ve
// kullanıcı giriş yapmamışsa, login sayfasına yönlendir
if (!in_array($current_page, $public_pages) && !girisYapmisMi()) {
    // Önce hatırlama token'ı kontrolü yap
    if (!hatirlamaTokeniKontrol($pdo)) {
        // Token kontrolü başarısız, login sayfasına yönlendir
        header('Location: login.php');
        exit();
    }
}
*/

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'efsaneba_serhan');  // cPanel Kullanıcı Adın
define('DB_PASS', 'Aooh8x!!!4189');       // cPanel Şifren
define('DB_NAME', 'efsaneba_uygulama');  // cPanel Veritabanı Adın

// Zaman dilimi ayarı
date_default_timezone_set('Europe/Istanbul');

// Karakter seti ayarları
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

// Veritabanı bağlantısı
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", 
        DB_USER, 
        DB_PASS
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Aşağıdaki komutlar çoğunlukla PDO'da "charset=utf8mb4" ile beraber gerekmez
    // ama yine de ekleyebilirsin.
    $db->exec("SET NAMES utf8mb4 COLLATE utf8mb4_turkish_ci");
    $db->exec("SET CHARACTER SET utf8mb4");
    $db->exec("SET COLLATION_CONNECTION = 'utf8mb4_turkish_ci'");

} catch (PDOException $e) {
    // Hata logla ve kullanıcıya basit bir mesaj ver
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
    die("Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyiniz.");
}

// Geçici oturum kontrolü (test amaçlı)
if (!isset($_SESSION['kullanici_id'])) {
    // Varsayılan kullanıcı bilgileri
    $_SESSION['kullanici_id'] = 1;
    $_SESSION['kullanici_adi'] = 'Admin Kullanıcı';
    $_SESSION['rol_id'] = 1;
}

// XSS koruma fonksiyonu
function guvenlik($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// CSRF token oluşturma
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

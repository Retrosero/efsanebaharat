<?php
// includes/auth.php

// Oturum başlatma (eğer başlamadıysa)
if (session_status() == PHP_SESSION_NONE) {
    // Oturum ve çerezlerin güvenliği için ayarlar (oturum başlamadan önce)
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    
    // Oturumu başlat
    session_start();
}

// Çerez süreleri
define('REMEMBER_COOKIE_NAME', 'remember_token');
define('REMEMBER_COOKIE_DURATION', 60 * 60 * 24 * 30); // 30 gün

/**
 * Kullanıcı giriş işlemini gerçekleştirir
 * 
 * @param string $eposta Kullanıcı e-posta adresi
 * @param string $sifre Kullanıcı şifresi
 * @param bool $beni_hatirla Beni hatırla seçeneği
 * @return bool|array Başarılı ise kullanıcı bilgileri, başarısız ise false
 */
function kullaniciGiris($pdo, $eposta, $sifre, $beni_hatirla = false) {
    try {
        // Kullanıcıyı veritabanında ara
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = :eposta AND aktif = 1");
        $stmt->execute([':eposta' => $eposta]);
        $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Kullanıcı bulunamadıysa veya şifre yanlışsa
        if (!$kullanici || !password_verify($sifre, $kullanici['sifre'])) {
            return false;
        }
        
        // Oturumu başlat
        $_SESSION['kullanici_id'] = $kullanici['id'];
        $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
        $_SESSION['eposta'] = $kullanici['eposta'];
        $_SESSION['rol_id'] = $kullanici['rol_id'];
        
        // Beni hatırla seçeneği işaretlendiyse
        if ($beni_hatirla) {
            // Güvenli token oluştur
            $token = bin2hex(random_bytes(32));
            $hash_token = password_hash($token, PASSWORD_DEFAULT);
            
            // Token'ı veritabanına kaydet
            $stmt = $pdo->prepare("
                UPDATE kullanicilar 
                SET remember_token = :token, token_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                WHERE id = :id
            ");
            $stmt->execute([
                ':token' => $hash_token,
                ':id' => $kullanici['id']
            ]);
            
            // Token'ı çereze kaydet
            setcookie(
                REMEMBER_COOKIE_NAME,
                $kullanici['id'] . ':' . $token,
                time() + REMEMBER_COOKIE_DURATION,
                '/',
                '',
                isset($_SERVER['HTTPS']),
                true
            );
        }
        
        return $kullanici;
    } catch (Exception $e) {
        error_log('Giriş hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Çerezden kullanıcıyı hatırla
 */
function hatirlamaTokeniKontrol($pdo) {
    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        list($kullanici_id, $token) = explode(':', $_COOKIE[REMEMBER_COOKIE_NAME], 2);
        
        $stmt = $pdo->prepare("
            SELECT * FROM kullanicilar 
            WHERE id = :id AND token_expires_at > NOW() AND aktif = 1
        ");
        $stmt->execute([':id' => $kullanici_id]);
        $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($kullanici && password_verify($token, $kullanici['remember_token'])) {
            // Kullanıcı doğrulandı, oturum bilgilerini oluştur
            $_SESSION['kullanici_id'] = $kullanici['id'];
            $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
            $_SESSION['eposta'] = $kullanici['eposta'];
            $_SESSION['rol_id'] = $kullanici['rol_id'];
            
            // Token süresini yenile
            $stmt = $pdo->prepare("
                UPDATE kullanicilar 
                SET token_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY) 
                WHERE id = :id
            ");
            $stmt->execute([':id' => $kullanici['id']]);
            
            return true;
        }
    }
    
    return false;
}

/**
 * Kullanıcının oturumunu kapatır
 */
function kullaniciCikis() {
    // Oturum değişkenlerini temizle
    unset($_SESSION['kullanici_id']);
    unset($_SESSION['kullanici_adi']);
    unset($_SESSION['eposta']);
    unset($_SESSION['rol_id']);
    
    // Hatırlama çerezini sil
    if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        setcookie(REMEMBER_COOKIE_NAME, '', time() - 3600, '/');
    }
    
    // Oturumu yok et
    session_destroy();
}

/**
 * Kullanıcının giriş yapmış olup olmadığını kontrol eder
 */
function girisYapmisMi() {
    return isset($_SESSION['kullanici_id']);
}

/**
 * Giriş yapmamış kullanıcıyı login sayfasına yönlendirir
 */
function girisGerekli() {
    if (!girisYapmisMi()) {
        header('Location: giris.php');
        exit();
    }
}

/**
 * Kullanıcının belirli bir sayfaya erişim yetkisi olup olmadığını kontrol eder
 * 
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $sayfa_url Kontrol edilecek sayfa URL'si (örn: 'urunler.php')
 * @return bool Erişim yetkisi varsa true, yoksa false
 */
function sayfaErisimKontrol($pdo, $sayfa_url = null) {
    // Kullanıcı giriş yapmamışsa
    if (!girisYapmisMi()) {
        return false;
    }
    
    // Sayfa URL'si belirtilmemişse mevcut sayfayı al
    if ($sayfa_url === null) {
        $sayfa_url = basename($_SERVER['PHP_SELF']);
    }
    
    // Yönetici rolü (rol_id = 1) her sayfaya erişebilir
    if ($_SESSION['rol_id'] == 1) {
        return true;
    }
    
    try {
        // Kullanıcının rolüne göre sayfa erişim izni var mı kontrol et
        $stmt = $pdo->prepare("
            SELECT rsi.izin 
            FROM rol_sayfa_izinleri rsi
            JOIN sayfalar s ON rsi.sayfa_id = s.id
            WHERE rsi.rol_id = :rol_id AND s.sayfa_url = :sayfa_url
        ");
        
        $stmt->execute([
            ':rol_id' => $_SESSION['rol_id'],
            ':sayfa_url' => $sayfa_url
        ]);
        
        $izin = $stmt->fetchColumn();
        
        // İzin varsa (1) veya izin kaydı yoksa (null)
        return ($izin == 1);
    } catch (Exception $e) {
        error_log('Sayfa erişim kontrolü hatası: ' . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının belirli bir sayfaya erişim yetkisi yoksa yönlendirir
 * 
 * @param PDO $pdo Veritabanı bağlantısı
 * @param string $sayfa_url Kontrol edilecek sayfa URL'si (örn: 'urunler.php')
 * @param string $yonlendir_url Erişim yetkisi yoksa yönlendirilecek URL (örn: 'index.php')
 */
function sayfaErisimGerekli($pdo, $sayfa_url = null, $yonlendir_url = 'index.php') {
    // Önce giriş kontrolü
    girisGerekli();
    
    // Sayfa erişim kontrolü
    if (!sayfaErisimKontrol($pdo, $sayfa_url)) {
        $_SESSION['hata_mesaji'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
        header("Location: " . $yonlendir_url);
        exit();
    }
}

// Kullanıcı giriş yapmış mı kontrol et
function checkAuth() {
    // Eğer kullanıcı giriş yapmamışsa
    if (!isset($_SESSION['kullanici_id'])) {
        // Giriş sayfasına yönlendir
        header('Location: giris.php');
        exit;
    }
}

// Giriş sayfası hariç tüm sayfalarda oturum kontrolü yap
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page != 'giris.php') {
    checkAuth();
}
?> 
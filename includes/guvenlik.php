<?php
// includes/guvenlik.php
// Ortak güvenlik yardımcıları: güvenlik başlıkları, CSRF (Origin/Referer) kontrolü,
// JSON/AJAX istek tespiti, yönetici kontrolü ve güvenli resim yükleme.

if (!function_exists('ajaxIstegiMi')) {

    /** İstek fetch/XHR/JSON isteği mi? (yönlendirme yerine JSON hata döndürmek için) */
    function ajaxIstegiMi()
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || stripos($accept, 'application/json') !== false
            || stripos($ctype, 'application/json') !== false
            || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/') !== false;
    }

    /** Yetkisiz isteği sonlandırır: AJAX ise JSON, değilse yönlendirme. */
    function yetkisizIstek($kod = 401, $mesaj = 'Oturum açmanız gerekiyor', $yonlendir = 'giris.php')
    {
        if (ajaxIstegiMi() || PHP_SAPI === 'cli') {
            http_response_code($kod);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'message' => $mesaj, 'error' => $mesaj]);
            exit;
        }
        if ($kod === 403) {
            $_SESSION['hata_mesaji'] = $mesaj;
        }
        // ajax/ altındaki dosyalar için kök dizine göre yönlendir
        $onEk = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/ajax/') !== false ? '../' : '';
        header('Location: ' . $onEk . $yonlendir);
        exit;
    }

    /** Uygulamanın kendi host bilgisi (proxy arkasında X-Forwarded-Host dahil). */
    function izinliHostlar(?array $server = null)
    {
        $server = $server ?? $_SERVER;
        $hostlar = [];
        foreach (['HTTP_HOST', 'HTTP_X_FORWARDED_HOST', 'SERVER_NAME'] as $anahtar) {
            if (!empty($server[$anahtar])) {
                foreach (explode(',', $server[$anahtar]) as $h) {
                    $h = strtolower(trim($h));
                    if ($h !== '') {
                        $hostlar[] = $h;
                        $hostlar[] = preg_replace('/:\d+$/', '', $h);
                    }
                }
            }
        }
        $ekstra = getenv('APP_ALLOWED_HOSTS');
        if ($ekstra) {
            foreach (explode(',', $ekstra) as $h) {
                $hostlar[] = strtolower(trim($h));
            }
        }
        return array_values(array_unique(array_filter($hostlar)));
    }

    /**
     * CSRF koruması: durum değiştiren isteklerde (POST/PUT/PATCH/DELETE) Origin veya Referer
     * başlığının uygulamanın kendi host'u olduğunu doğrular (OWASP "standart başlıklarla kaynak doğrulama").
     * Tarayıcılar bu başlıkları siteler arası isteklerde her zaman gönderir.
     */
    function csrfKaynakGecerliMi(?array $server = null)
    {
        $server = $server ?? $_SERVER;
        $metod = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        if (in_array($metod, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $kaynak = $server['HTTP_ORIGIN'] ?? '';
        if ($kaynak === '' || $kaynak === 'null') {
            $kaynak = $server['HTTP_REFERER'] ?? '';
        }
        if ($kaynak === '') {
            // Başlık yoksa (eski istemci / gizlilik eklentisi) form token'ı ile doğrula
            $token = $server['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
            return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
        }

        $host = parse_url($kaynak, PHP_URL_HOST);
        if (!$host) {
            return false;
        }
        $port = parse_url($kaynak, PHP_URL_PORT);
        $host = strtolower($host);
        $hostPort = $port ? $host . ':' . $port : $host;

        $izinli = izinliHostlar($server);

        return in_array($hostPort, $izinli, true) || in_array($host, $izinli, true);
    }

    /** Oturumdaki CSRF token ile karşılaştırır (GET ile yapılan silme bağlantıları için). */
    function csrfTokenGecerli($token)
    {
        return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
    }

    function csrfKontrol()
    {
        if (PHP_SAPI === 'cli') {
            return;
        }
        if (!csrfKaynakGecerliMi()) {
            yetkisizIstek(403, 'Geçersiz istek kaynağı (CSRF koruması)', 'index.php');
        }
    }

    /** Temel güvenlik başlıkları */
    function guvenlikBasliklariGonder()
    {
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(self), microphone=(), geolocation=()');
        header_remove('X-Powered-By');
    }

    /** Yönetici (rol_id = 1) değilse erişimi engeller (kurulum / bakım betikleri için). */
    function yoneticiGerekli()
    {
        if (empty($_SESSION['kullanici_id'])) {
            yetkisizIstek(401);
        }
        if ((int)($_SESSION['rol_id'] ?? 0) !== 1) {
            yetkisizIstek(403, 'Bu işlem için yönetici yetkisi gereklidir.', 'index.php');
        }
    }

    /**
     * header.php içermeyen uç noktalar (AJAX / form hedefleri) için rol tabanlı sayfa yetkisi.
     * Her uç nokta, yetkisini devraldığı ekranla eşleştirilir; header.php'deki kontrolle aynı kural uygulanır.
     */
    define('UC_NOKTA_YETKI_HARITASI', [
        'satis_kaydet.php' => 'satis.php',
        'urun_sil.php' => 'urunler.php',
        'filter_products.php' => 'urunler.php',
        'delete_product_image.php' => 'urunler.php',
        'musteri_guncelle.php' => 'musteriler.php',
        'gunsonu_verileri.php' => 'gunsonu.php',
        'urunleri_disa_aktar_xml.php' => 'urunler.php',
    ]);

    function ucNoktaYetkiKontrol($pdo)
    {
        if (PHP_SAPI === 'cli' || empty($_SESSION['kullanici_id'])) {
            return;
        }
        $betik = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (!isset(UC_NOKTA_YETKI_HARITASI[$betik]) || !function_exists('sayfaErisimKontrol')) {
            return;
        }
        if (!sayfaErisimKontrol($pdo, UC_NOKTA_YETKI_HARITASI[$betik])) {
            yetkisizIstek(403, 'Bu işlem için yetkiniz bulunmamaktadır.', 'error.php');
        }
    }

    /** Kaba kuvvet (brute-force) koruması: IP başına 15 dakikada en fazla 10 hatalı deneme. */
    function girisDenemeDosyasi($ip = null) {
        $ip = $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'efsane_giris_' . sha1($ip) . '.json';
    }

    function girisKilitliMi($ip = null, $maksDeneme = 10, $sure = 900) {
        $dosya = girisDenemeDosyasi($ip);
        if (!is_file($dosya)) {
            return false;
        }
        $veri = json_decode((string)@file_get_contents($dosya), true);
        if (!is_array($veri) || (time() - ($veri['ilk'] ?? 0)) > $sure) {
            @unlink($dosya);
            return false;
        }
        return ($veri['sayi'] ?? 0) >= $maksDeneme;
    }

    function girisBasarisizKaydet($ip = null, $sure = 900) {
        $dosya = girisDenemeDosyasi($ip);
        $veri = is_file($dosya) ? json_decode((string)@file_get_contents($dosya), true) : null;
        if (!is_array($veri) || (time() - ($veri['ilk'] ?? 0)) > $sure) {
            $veri = ['ilk' => time(), 'sayi' => 0];
        }
        $veri['sayi']++;
        @file_put_contents($dosya, json_encode($veri), LOCK_EX);
    }

    function girisDenemeleriniSifirla($ip = null) {
        @unlink(girisDenemeDosyasi($ip));
    }

    /** HTML çıktısı için kaçış */
    function e($deger)
    {
        return htmlspecialchars((string)$deger, ENT_QUOTES, 'UTF-8');
    }

    /** <script> içine güvenle gömülecek JSON */
    function jsonScript($veri, $ekBayraklar = 0)
    {
        return json_encode($veri, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | $ekBayraklar);
    }

    define('IZINLI_RESIM_UZANTILARI', ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif']);

    /**
     * Yüklenen dosyanın gerçekten izin verilen bir resim olup olmadığını denetler.
     * @return string|false Güvenli uzantı veya false
     */
    function resimDosyasiDogrula(array $dosya, $maksBoyut = 10485760)
    {
        if (!isset($dosya['error'], $dosya['tmp_name'], $dosya['name']) || $dosya['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        if (($dosya['size'] ?? 0) <= 0 || $dosya['size'] > $maksBoyut) {
            return false;
        }
        $uzanti = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
        if (!isset(IZINLI_RESIM_UZANTILARI[$uzanti])) {
            return false;
        }
        $bilgi = @getimagesize($dosya['tmp_name']);
        if ($bilgi === false || !in_array($bilgi['mime'] ?? '', array_values(IZINLI_RESIM_UZANTILARI), true)) {
            return false;
        }
        return $uzanti === 'jpeg' ? 'jpg' : $uzanti;
    }

    /**
     * Resmi doğrulayıp rastgele isimle hedef klasöre taşır.
     * @return string|false Kaydedilen göreli yol veya false
     */
    function guvenliResimYukle(array $dosya, $hedefKlasor)
    {
        $uzanti = resimDosyasiDogrula($dosya);
        if ($uzanti === false) {
            return false;
        }
        $hedefKlasor = rtrim($hedefKlasor, '/') . '/';
        if (!is_dir($hedefKlasor) && !@mkdir($hedefKlasor, 0755, true)) {
            return false;
        }
        $yol = $hedefKlasor . bin2hex(random_bytes(16)) . '.' . $uzanti;
        if (!is_uploaded_file($dosya['tmp_name']) || !move_uploaded_file($dosya['tmp_name'], $yol)) {
            return false;
        }
        return $yol;
    }

    /** Sadece uploads/ altındaki bir dosyanın silinmesine izin verir (dizin geçişini engeller). */
    function guvenliYuklemeSil($yol)
    {
        if (!is_string($yol) || $yol === '') {
            return false;
        }
        $kok = realpath(__DIR__ . '/../uploads');
        $gercek = realpath(__DIR__ . '/../' . ltrim($yol, '/'));
        if ($kok === false || $gercek === false || strpos($gercek, $kok . DIRECTORY_SEPARATOR) !== 0 || !is_file($gercek)) {
            return false;
        }
        return @unlink($gercek);
    }
}

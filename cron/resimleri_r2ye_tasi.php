<?php
// cron/resimleri_r2ye_tasi.php
// Ürün resimlerini (uploads/...) Cloudflare R2'ye taşır ve veritabanındaki adresleri günceller.
// Yalnızca komut satırından çalışır. Tekrar çalıştırmak güvenlidir (taşınmış resimler atlanır).
//
// Kullanım:
//   php cron/resimleri_r2ye_tasi.php --kuru                       # sadece ne yapılacağını göster
//   php cron/resimleri_r2ye_tasi.php                              # yerel dosyaları taşı
//   php cron/resimleri_r2ye_tasi.php --kaynak-url=https://panel.efsanebaharat.com
//        # yerelde bulunmayan dosyaları bu siteden indirip taşı

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$secenekler = getopt('', ['kuru', 'kaynak-url:', 'limit:']);
$kuru = isset($secenekler['kuru']);
$kaynakUrl = isset($secenekler['kaynak-url']) ? rtrim($secenekler['kaynak-url'], '/') : null;
$limit = isset($secenekler['limit']) ? max(1, (int)$secenekler['limit']) : PHP_INT_MAX;

if (!r2ResimEtkin()) {
    fwrite(STDERR, "R2 yapılandırılmamış. R2_ACCOUNT_ID, R2_ACCESS_KEY_ID, R2_SECRET_ACCESS_KEY, R2_BUCKET ve R2_PUBLIC_URL ortam değişkenlerini ayarlayın.\n");
    exit(1);
}
if ($kaynakUrl !== null && !preg_match('#^https?://#', $kaynakUrl)) {
    fwrite(STDERR, "--kaynak-url http:// veya https:// ile başlamalı\n");
    exit(1);
}

$istemci = R2Istemci::ortamdan();
$kova = getenv('R2_BUCKET');
$kok = realpath(__DIR__ . '/..');

$kolonlar = ['resim_url'];
for ($i = 1; $i <= 10; $i++) {
    $kolonlar[] = "resim_url_$i";
}
$mevcutKolonlar = $pdo->query('SHOW COLUMNS FROM urunler')->fetchAll(PDO::FETCH_COLUMN);
$kolonlar = array_values(array_intersect($kolonlar, $mevcutKolonlar));

/** Resim içeriğini yerelden ya da kaynak siteden alır; geçici dosya yolunu döndürür. */
function resimGetir(string $yol, string $kok, ?string $kaynakUrl): ?string
{
    $goreli = ltrim($yol, '/');
    if (strpos($goreli, 'uploads/') !== 0 || strpos($goreli, '..') !== false) {
        return null; // sadece uploads/ altındaki göreli yollar taşınır
    }
    $yerel = $kok . '/' . $goreli;
    if (is_file($yerel)) {
        return $yerel;
    }
    if ($kaynakUrl === null) {
        return null;
    }
    $gecici = tempnam(sys_get_temp_dir(), 'r2resim');
    $ch = curl_init($kaynakUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $goreli))));
    $f = fopen($gecici, 'wb');
    curl_setopt_array($ch, [CURLOPT_FILE => $f, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 60, CURLOPT_FAILONERROR => true]);
    $tamam = curl_exec($ch);
    $durum = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    fclose($f);
    if (!$tamam || $durum !== 200 || filesize($gecici) === 0) {
        @unlink($gecici);
        return null;
    }
    return $gecici;
}

$tasinan = []; // eski yol => yeni adres (aynı resim birden çok üründe olabilir)
$sayac = ['tasindi' => 0, 'atlandi' => 0, 'bulunamadi' => 0, 'hata' => 0, 'guncellenen_alan' => 0];
$urunler = $pdo->query('SELECT id, ' . implode(', ', $kolonlar) . ' FROM urunler ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);

foreach ($urunler as $urun) {
    foreach ($kolonlar as $kolon) {
        $yol = trim((string)$urun[$kolon]);
        if ($yol === '' || preg_match('#^https?://#', $yol)) {
            continue; // boş ya da zaten bir adres (R2 veya harici)
        }
        if (!isset($tasinan[$yol])) {
            if (count($tasinan) >= $limit) {
                break 2;
            }
            $dosya = resimGetir($yol, $kok, $kaynakUrl);
            if ($dosya === null) {
                $sayac['bulunamadi']++;
                echo "BULUNAMADI  #{$urun['id']} $kolon: $yol\n";
                $tasinan[$yol] = false;
                continue;
            }
            $bilgi = @getimagesize($dosya);
            $mime = $bilgi['mime'] ?? '';
            $uzanti = array_search($mime, IZINLI_RESIM_UZANTILARI, true);
            if ($uzanti === false) {
                $sayac['atlandi']++;
                echo "RESİM DEĞİL #{$urun['id']} $kolon: $yol\n";
                $tasinan[$yol] = false;
                if (strpos($dosya, $kok) !== 0) @unlink($dosya);
                continue;
            }
            $klasor = strpos(ltrim($yol, '/'), 'uploads/images/') === 0 ? 'images' : 'products';
            // Aynı dosya her çalıştırmada aynı anahtara gider (tekrar çalıştırmada kopya oluşmaz)
            $anahtar = $klasor . '/' . substr(hash_file('sha256', $dosya), 0, 32) . '.' . ($uzanti === 'jpeg' ? 'jpg' : $uzanti);
            if ($kuru) {
                echo "TAŞINACAK   #{$urun['id']} $kolon: $yol -> " . r2GenelAdres($anahtar) . "\n";
                $tasinan[$yol] = r2GenelAdres($anahtar);
            } else {
                try {
                    $istemci->yukle($kova, $anahtar, $dosya, $mime, true, ['cache-control' => 'public, max-age=31536000, immutable']);
                    $tasinan[$yol] = r2GenelAdres($anahtar);
                    $sayac['tasindi']++;
                    echo "TAŞINDI     #{$urun['id']} $kolon: $yol\n";
                } catch (R2Hatasi $e) {
                    $sayac['hata']++;
                    $tasinan[$yol] = false;
                    echo "HATA        #{$urun['id']} $kolon: $yol ({$e->getMessage()})\n";
                }
            }
            if (strpos($dosya, $kok) !== 0) {
                @unlink($dosya); // indirilen geçici dosya
            }
        }
        if ($tasinan[$yol] && !$kuru) {
            $pdo->prepare("UPDATE urunler SET `$kolon` = ? WHERE id = ? AND `$kolon` = ?")->execute([$tasinan[$yol], $urun['id'], $urun[$kolon]]);
            $sayac['guncellenen_alan']++;
        }
    }
}

echo "\nÖzet" . ($kuru ? ' (KURU ÇALIŞMA, hiçbir şey değişmedi)' : '') . ":\n";
foreach ($sayac as $k => $v) {
    echo str_pad($k, 18) . ": $v\n";
}
exit($sayac['hata'] > 0 ? 2 : 0);

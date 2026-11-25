<?php
// includes/r2.php
// Cloudflare R2 (S3 uyumlu) için bağımlılıksız küçük istemci: AWS Signature V4 ile
// nesne yükleme, silme, listeleme ve süreli (presigned) indirme bağlantısı.
//
// Ortam değişkenleri:
//   R2_ACCOUNT_ID        Cloudflare hesap kimliği (endpoint bundan üretilir)
//   R2_ACCESS_KEY_ID     R2 API token erişim anahtarı
//   R2_SECRET_ACCESS_KEY R2 API token gizli anahtarı
//   R2_BUCKET            Resimlerin yükleneceği kova (herkese açık okunur olmalı)
//   R2_PUBLIC_URL        Resim kovasının herkese açık adresi (r2.dev veya özel alan adı)
//   R2_BACKUP_BUCKET     Veritabanı yedeklerinin kovası (GİZLİ olmalı, resim kovasından ayrı)
//   R2_ENDPOINT          (isteğe bağlı) Özel endpoint, ör. yerel test için MinIO
//   R2_YEDEK_SAKLAMA_GUN (isteğe bağlı) Yedeklerin saklanacağı gün sayısı, varsayılan 30

class R2Hatasi extends RuntimeException {}

class R2Istemci
{
    private string $endpoint;
    private string $erisimAnahtari;
    private string $gizliAnahtar;
    private string $bolge;

    public function __construct(string $endpoint, string $erisimAnahtari, string $gizliAnahtar, string $bolge = 'auto')
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->erisimAnahtari = $erisimAnahtari;
        $this->gizliAnahtar = $gizliAnahtar;
        $this->bolge = $bolge;
    }

    /** Ortam değişkenlerinden istemci; yapılandırılmamışsa null. */
    public static function ortamdan(): ?self
    {
        $anahtar = getenv('R2_ACCESS_KEY_ID');
        $gizli = getenv('R2_SECRET_ACCESS_KEY');
        $endpoint = getenv('R2_ENDPOINT');
        if (!$endpoint && getenv('R2_ACCOUNT_ID')) {
            $endpoint = 'https://' . getenv('R2_ACCOUNT_ID') . '.r2.cloudflarestorage.com';
        }
        if (!$anahtar || !$gizli || !$endpoint) {
            return null;
        }
        return new self($endpoint, $anahtar, $gizli, getenv('R2_REGION') ?: 'auto');
    }

    // ---------------------------------------------------------------- imzalama

    /** S3 için yol kodlaması: her parça RFC 3986'ya göre, '/' korunur. */
    public static function yolKodla(string $yol): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $yol)));
    }

    public static function sorguKodla(array $sorgu): string
    {
        ksort($sorgu, SORT_STRING);
        $parcalar = [];
        foreach ($sorgu as $k => $v) {
            $parcalar[] = rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
        }
        return implode('&', $parcalar);
    }

    private function imzaAnahtari(string $tarih, string $servis = 's3'): string
    {
        $k = hash_hmac('sha256', $tarih, 'AWS4' . $this->gizliAnahtar, true);
        $k = hash_hmac('sha256', $this->bolge, $k, true);
        $k = hash_hmac('sha256', $servis, $k, true);
        return hash_hmac('sha256', 'aws4_request', $k, true);
    }

    /**
     * AWS SigV4 imzası. $basliklar küçük harfli anahtarlarla verilir ve 'host' içermelidir.
     * @return array{imza:string, imzaliBasliklar:string, kapsam:string}
     */
    public function imzala(string $metod, string $kodluYol, array $sorgu, array $basliklar, string $yukOzeti, string $amzTarih): array
    {
        ksort($basliklar, SORT_STRING);
        $kanonikBasliklar = '';
        foreach ($basliklar as $k => $v) {
            $kanonikBasliklar .= strtolower($k) . ':' . trim(preg_replace('/\s+/', ' ', (string)$v)) . "\n";
        }
        $imzaliBasliklar = implode(';', array_map('strtolower', array_keys($basliklar)));
        $kanonikIstek = implode("\n", [$metod, $kodluYol, self::sorguKodla($sorgu), $kanonikBasliklar, $imzaliBasliklar, $yukOzeti]);

        $gun = substr($amzTarih, 0, 8);
        $kapsam = "$gun/{$this->bolge}/s3/aws4_request";
        $imzalanacak = "AWS4-HMAC-SHA256\n$amzTarih\n$kapsam\n" . hash('sha256', $kanonikIstek);
        $imza = hash_hmac('sha256', $imzalanacak, $this->imzaAnahtari($gun));

        return ['imza' => $imza, 'imzaliBasliklar' => $imzaliBasliklar, 'kapsam' => $kapsam];
    }

    private function host(): string
    {
        $p = parse_url($this->endpoint);
        return $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    private function kodluYol(string $kova, string $anahtar = ''): string
    {
        $tabanYol = rtrim(parse_url($this->endpoint, PHP_URL_PATH) ?? '', '/');
        return $tabanYol . '/' . rawurlencode($kova) . ($anahtar !== '' ? '/' . self::yolKodla($anahtar) : '');
    }

    // ---------------------------------------------------------------- istekler

    /**
     * @param string|resource|null $govde
     * @return array{durum:int, govde:string, basliklar:array}
     */
    private function istek(string $metod, string $kova, string $anahtar = '', array $sorgu = [], $govde = null, array $ekBasliklar = [], ?string $yukOzeti = null): array
    {
        $amzTarih = gmdate('Ymd\THis\Z');
        if ($yukOzeti === null) {
            $yukOzeti = is_string($govde) ? hash('sha256', $govde) : hash('sha256', '');
        }
        $basliklar = array_change_key_case($ekBasliklar, CASE_LOWER) + [
            'host' => $this->host(),
            'x-amz-content-sha256' => $yukOzeti,
            'x-amz-date' => $amzTarih,
        ];
        $kodluYol = $this->kodluYol($kova, $anahtar);
        $imza = $this->imzala($metod, $kodluYol, $sorgu, $basliklar, $yukOzeti, $amzTarih);
        $basliklar['authorization'] = sprintf(
            'AWS4-HMAC-SHA256 Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            $this->erisimAnahtari, $imza['kapsam'], $imza['imzaliBasliklar'], $imza['imza']
        );

        $p = parse_url($this->endpoint);
        $url = $p['scheme'] . '://' . $this->host() . $kodluYol . ($sorgu ? '?' . self::sorguKodla($sorgu) : '');

        $ch = curl_init($url);
        $httpBasliklar = [];
        foreach ($basliklar as $k => $v) {
            if ($k !== 'host') {
                $httpBasliklar[] = "$k: $v";
            }
        }
        $yanitBasliklari = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metod,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge($httpBasliklar, ['Expect:']),
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_HEADERFUNCTION => function ($c, $satir) use (&$yanitBasliklari) {
                if (strpos($satir, ':') !== false) {
                    [$k, $v] = explode(':', $satir, 2);
                    $yanitBasliklari[strtolower(trim($k))] = trim($v);
                }
                return strlen($satir);
            },
        ]);
        if (is_resource($govde)) {
            $boyut = fstat($govde)['size'];
            curl_setopt_array($ch, [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $govde, CURLOPT_INFILESIZE => $boyut]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metod);
        } elseif (is_string($govde)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $govde);
        }
        $yanit = curl_exec($ch);
        if ($yanit === false) {
            $hata = curl_error($ch);
            curl_close($ch);
            throw new R2Hatasi("R2 bağlantı hatası: $hata");
        }
        $durum = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['durum' => $durum, 'govde' => (string)$yanit, 'basliklar' => $yanitBasliklari];
    }

    private static function hataMesaji(array $yanit): string
    {
        if (preg_match('#<Code>(.*?)</Code>.*?<Message>(.*?)</Message>#s', $yanit['govde'], $m)) {
            return "{$m[1]}: {$m[2]}";
        }
        return 'HTTP ' . $yanit['durum'];
    }

    /** Dosya ya da metin yükler. $kaynak bir dosya yolu ise akış olarak gönderilir. */
    public function yukle(string $kova, string $anahtar, string $kaynak, string $icerikTuru = 'application/octet-stream', bool $dosyaMi = false, array $ekBasliklar = []): void
    {
        $basliklar = ['content-type' => $icerikTuru] + $ekBasliklar;
        if ($dosyaMi) {
            $tutamak = fopen($kaynak, 'rb');
            if (!$tutamak) {
                throw new R2Hatasi("Dosya açılamadı: $kaynak");
            }
            try {
                $yanit = $this->istek('PUT', $kova, $anahtar, [], $tutamak, $basliklar, hash_file('sha256', $kaynak));
            } finally {
                fclose($tutamak);
            }
        } else {
            $yanit = $this->istek('PUT', $kova, $anahtar, [], $kaynak, $basliklar);
        }
        if ($yanit['durum'] !== 200) {
            throw new R2Hatasi("Yükleme başarısız ($anahtar): " . self::hataMesaji($yanit));
        }
    }

    public function sil(string $kova, string $anahtar): void
    {
        $yanit = $this->istek('DELETE', $kova, $anahtar);
        if (!in_array($yanit['durum'], [200, 204, 404], true)) {
            throw new R2Hatasi("Silme başarısız ($anahtar): " . self::hataMesaji($yanit));
        }
    }

    /** @return array<int, array{anahtar:string, boyut:int, tarih:string}> */
    public function listele(string $kova, string $onEk = ''): array
    {
        $sonuc = [];
        $devam = null;
        do {
            $sorgu = ['list-type' => '2', 'prefix' => $onEk, 'max-keys' => '1000'];
            if ($devam) {
                $sorgu['continuation-token'] = $devam;
            }
            $yanit = $this->istek('GET', $kova, '', $sorgu);
            if ($yanit['durum'] !== 200) {
                throw new R2Hatasi('Listeleme başarısız: ' . self::hataMesaji($yanit));
            }
            $xml = @simplexml_load_string($yanit['govde']);
            if ($xml === false) {
                throw new R2Hatasi('Listeleme yanıtı okunamadı');
            }
            foreach ($xml->Contents as $c) {
                $sonuc[] = ['anahtar' => (string)$c->Key, 'boyut' => (int)$c->Size, 'tarih' => (string)$c->LastModified];
            }
            $devam = ((string)$xml->IsTruncated === 'true') ? (string)$xml->NextContinuationToken : null;
        } while ($devam);
        return $sonuc;
    }

    /** Süreli indirme bağlantısı (gizli kovadaki yedekler için). */
    public function sureliBaglanti(string $kova, string $anahtar, int $sure = 300, ?string $amzTarih = null): string
    {
        $amzTarih = $amzTarih ?? gmdate('Ymd\THis\Z');
        $kodluYol = $this->kodluYol($kova, $anahtar);
        $kapsam = substr($amzTarih, 0, 8) . "/{$this->bolge}/s3/aws4_request";
        $sorgu = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->erisimAnahtari . '/' . $kapsam,
            'X-Amz-Date' => $amzTarih,
            'X-Amz-Expires' => (string)$sure,
            'X-Amz-SignedHeaders' => 'host',
        ];
        $imza = $this->imzala('GET', $kodluYol, $sorgu, ['host' => $this->host()], 'UNSIGNED-PAYLOAD', $amzTarih);
        $sorgu['X-Amz-Signature'] = $imza['imza'];
        $p = parse_url($this->endpoint);
        return $p['scheme'] . '://' . $this->host() . $kodluYol . '?' . self::sorguKodla($sorgu);
    }
}

// -------------------------------------------------------------------- yardımcılar

/** Resimler R2'ye mi yüklenecek? */
function r2ResimEtkin(): bool
{
    return getenv('R2_BUCKET') && getenv('R2_PUBLIC_URL') && R2Istemci::ortamdan() !== null;
}

function r2YedekEtkin(): bool
{
    return getenv('R2_BACKUP_BUCKET') && R2Istemci::ortamdan() !== null;
}

function r2GenelAdres(string $anahtar): string
{
    return rtrim(getenv('R2_PUBLIC_URL'), '/') . '/' . R2Istemci::yolKodla($anahtar);
}

/** Bir resim adresi bizim R2 kovamıza mı ait? Öyleyse nesne anahtarını döndürür. */
function r2AnahtarCoz(string $adres): ?string
{
    $taban = rtrim((string)getenv('R2_PUBLIC_URL'), '/');
    if ($taban === '' || strpos($adres, $taban . '/') !== 0) {
        return null;
    }
    $anahtar = rawurldecode(substr($adres, strlen($taban) + 1));
    // Yalnızca resim klasörlerindeki, dizin geçişi içermeyen anahtarlar
    if (!preg_match('#^(products|images)/[A-Za-z0-9._-]+$#', $anahtar)) {
        return null;
    }
    return $anahtar;
}

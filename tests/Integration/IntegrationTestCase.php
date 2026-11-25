<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Çalışan uygulamaya (APP_URL) HTTP istekleri atan ve test veritabanını hazırlayan temel sınıf.
 * docker-compose.test.yml ile çalıştırılır; APP_URL tanımlı değilse testler atlanır.
 */
abstract class IntegrationTestCase extends TestCase
{
    public const ADMIN_EPOSTA = 'test.admin@example.test';
    public const CALISAN_EPOSTA = 'test.calisan@example.test';
    public const PAROLA = 'Test-Parola-123!';

    protected static ?PDO $pdo = null;
    protected static array $fixture = [];
    private ?string $cerezDosyasi = null;

    public static function setUpBeforeClass(): void
    {
        if (!getenv('APP_URL')) {
            return;
        }
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST'), getenv('DB_NAME')),
                getenv('DB_USER'),
                getenv('DB_PASS'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            self::fixtureHazirla();
        }
    }

    protected function setUp(): void
    {
        if (!getenv('APP_URL')) {
            $this->markTestSkipped('APP_URL tanımlı değil (docker-compose.test.yml ile çalıştırın).');
        }
        $this->cerezDosyasi = tempnam(sys_get_temp_dir(), 'cerez');
    }

    protected function tearDown(): void
    {
        if ($this->cerezDosyasi) {
            @unlink($this->cerezDosyasi);
        }
    }

    private static function fixtureHazirla(): void
    {
        $pdo = self::$pdo;
        $hash = password_hash(self::PAROLA, PASSWORD_DEFAULT);
        foreach ([[self::ADMIN_EPOSTA, 'Test Yonetici', 1], [self::CALISAN_EPOSTA, 'Test Calisan', 2]] as [$eposta, $ad, $rol]) {
            $pdo->prepare('DELETE FROM kullanicilar WHERE eposta = ?')->execute([$eposta]);
            $pdo->prepare('INSERT INTO kullanicilar (kullanici_adi, eposta, sifre, rol_id, aktif) VALUES (?, ?, ?, ?, 1)')
                ->execute([$ad, $eposta, $hash, $rol]);
        }

        $urun = $pdo->prepare('INSERT INTO urunler (urun_adi, urun_kodu, birim_fiyat, stok_miktari, olcum_birimi, kdv_orani) VALUES (?, ?, ?, ?, ?, 0)');
        $kod = 'TST' . bin2hex(random_bytes(3));
        $urun->execute(['Test Karabiber (kg)', $kod . 'K', 500, 10, 'kg']);
        self::$fixture['kg_urun'] = (int)$pdo->lastInsertId();
        $urun->execute(['Test Safran (gr)', $kod . 'G', 0.4, 5000, 'gr']);
        self::$fixture['gr_urun'] = (int)$pdo->lastInsertId();
        $urun->execute(['Test Kavanoz (adet)', $kod . 'A', 25, 4, 'adet']);
        self::$fixture['adet_urun'] = (int)$pdo->lastInsertId();

        $pdo->prepare('INSERT INTO musteriler (ad, soyad, cari_bakiye) VALUES (?, ?, 0)')->execute(['Test', 'Musteri']);
        self::$fixture['musteri'] = (int)$pdo->lastInsertId();
    }

    protected static function urunStok(int $id): float
    {
        $s = self::$pdo->prepare('SELECT stok_miktari FROM urunler WHERE id = ?');
        $s->execute([$id]);
        return (float)$s->fetchColumn();
    }

    /**
     * @return array{status:int, headers:array<string,string>, body:string}
     */
    protected function istek(string $metod, string $yol, $govde = null, array $basliklar = []): array
    {
        $url = rtrim(getenv('APP_URL'), '/') . '/' . ltrim($yol, '/');
        $ch = curl_init($url);
        $hostBaslik = parse_url(getenv('APP_URL'), PHP_URL_HOST);
        $varsayilan = ['Origin: ' . rtrim(getenv('APP_URL'), '/')];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $metod,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cerezDosyasi,
            CURLOPT_COOKIEFILE => $this->cerezDosyasi,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => array_merge($varsayilan, $basliklar),
        ]);
        if ($govde !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($govde) ? http_build_query($govde) : $govde);
        }
        $yanit = curl_exec($ch);
        $this->assertNotFalse($yanit, "İstek başarısız: $url " . curl_error($ch));
        $baslikBoyu = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $headers = [];
        foreach (explode("\r\n", substr($yanit, 0, $baslikBoyu)) as $satir) {
            if (strpos($satir, ':') !== false) {
                [$k, $v] = explode(':', $satir, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        unset($hostBaslik);
        return ['status' => $status, 'headers' => $headers, 'body' => substr($yanit, $baslikBoyu)];
    }

    protected function get(string $yol, array $basliklar = []): array
    {
        return $this->istek('GET', $yol, null, $basliklar);
    }

    protected function postJson(string $yol, array $veri, array $basliklar = []): array
    {
        return $this->istek('POST', $yol, json_encode($veri), array_merge(['Content-Type: application/json', 'Accept: application/json'], $basliklar));
    }

    protected function girisYap(string $eposta = self::ADMIN_EPOSTA, string $parola = self::PAROLA): void
    {
        $this->get('giris.php');
        $r = $this->istek('POST', 'giris.php', ['eposta' => $eposta, 'sifre' => $parola, 'beni_hatirla' => '1']);
        $this->assertSame(302, $r['status'], 'Giriş başarısız: ' . substr(strip_tags($r['body']), 0, 300));
        $this->assertStringContainsString('index.php', $r['headers']['location'] ?? '');
    }

    protected function assertGirisSayfasinaYonlendirir(array $r, string $yol): void
    {
        if ($r['status'] === 401) {
            $this->assertStringContainsString('Oturum', $r['body'], $yol);
            return;
        }
        $this->assertSame(302, $r['status'], "$yol giriş yapmadan erişilebilir olmamalı (HTTP {$r['status']})");
        $this->assertMatchesRegularExpression('#giris\.php$#', $r['headers']['location'] ?? '', $yol);
    }
}

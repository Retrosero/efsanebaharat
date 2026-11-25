<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use R2Istemci;

/**
 * İmza testleri AWS S3 SigV4 belgelerindeki resmi örnek değerleri kullanır
 * (examplebucket / AKIAIOSFODNN7EXAMPLE, 24 Mayıs 2013).
 */
final class R2Test extends TestCase
{
    private const ANAHTAR = 'AKIAIOSFODNN7EXAMPLE';
    private const GIZLI = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';

    private array $eskiOrtam = [];

    protected function tearDown(): void
    {
        foreach ($this->eskiOrtam as $k => $v) {
            putenv($v === false ? $k : "$k=$v");
        }
        $this->eskiOrtam = [];
    }

    private function ortam(array $degerler): void
    {
        foreach ($degerler as $k => $v) {
            if (!array_key_exists($k, $this->eskiOrtam)) {
                $this->eskiOrtam[$k] = getenv($k);
            }
            putenv($v === null ? $k : "$k=$v");
        }
    }

    public function testGetObjectImzasiAwsOrnegiyleAyni(): void
    {
        $istemci = new R2Istemci('https://examplebucket.s3.amazonaws.com', self::ANAHTAR, self::GIZLI, 'us-east-1');
        $imza = $istemci->imzala('GET', '/test.txt', [], [
            'host' => 'examplebucket.s3.amazonaws.com',
            'range' => 'bytes=0-9',
            'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'x-amz-date' => '20130524T000000Z',
        ], 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', '20130524T000000Z');

        $this->assertSame('host;range;x-amz-content-sha256;x-amz-date', $imza['imzaliBasliklar']);
        $this->assertSame('20130524/us-east-1/s3/aws4_request', $imza['kapsam']);
        $this->assertSame('f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41', $imza['imza']);
    }

    public function testSureliBaglantiImzasiAwsOrnegiyleAyni(): void
    {
        // Yol-stili istemci, AWS örneği sanal-host stilinde: aynı kanonik isteği üretmek için imzala() doğrudan
        $istemci = new R2Istemci('https://examplebucket.s3.amazonaws.com', self::ANAHTAR, self::GIZLI, 'us-east-1');
        $sorgu = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => self::ANAHTAR . '/20130524/us-east-1/s3/aws4_request',
            'X-Amz-Date' => '20130524T000000Z',
            'X-Amz-Expires' => '86400',
            'X-Amz-SignedHeaders' => 'host',
        ];
        $imza = $istemci->imzala('GET', '/test.txt', $sorgu, ['host' => 'examplebucket.s3.amazonaws.com'], 'UNSIGNED-PAYLOAD', '20130524T000000Z');
        $this->assertSame('aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404', $imza['imza']);
    }

    public function testSureliBaglantiYapisi(): void
    {
        $istemci = new R2Istemci('https://hesap.r2.cloudflarestorage.com', 'AK', 'SK');
        $url = $istemci->sureliBaglanti('yedekler', 'veritabani/2026/11/yedek 1.sql.gz', 300, '20261125T120000Z');
        $this->assertStringStartsWith('https://hesap.r2.cloudflarestorage.com/yedekler/veritabani/2026/11/yedek%201.sql.gz?', $url);
        $this->assertStringContainsString('X-Amz-Expires=300', $url);
        $this->assertStringContainsString('X-Amz-Credential=AK%2F20261125%2Fauto%2Fs3%2Faws4_request', $url);
        $this->assertMatchesRegularExpression('/X-Amz-Signature=[0-9a-f]{64}(&|$)/', $url);
    }

    public function testYolKodlama(): void
    {
        $this->assertSame('products/a%20b/%C3%BCr%C3%BCn.jpg', R2Istemci::yolKodla('products/a b/ürün.jpg'));
        $this->assertSame('a=1&b=x%2Fy&list-type=2', R2Istemci::sorguKodla(['list-type' => '2', 'b' => 'x/y', 'a' => '1']));
    }

    public function testOrtamYoksaDevreDisi(): void
    {
        $this->ortam(['R2_ACCESS_KEY_ID' => null, 'R2_SECRET_ACCESS_KEY' => null, 'R2_ENDPOINT' => null, 'R2_ACCOUNT_ID' => null, 'R2_BUCKET' => null, 'R2_PUBLIC_URL' => null, 'R2_BACKUP_BUCKET' => null]);
        $this->assertNull(R2Istemci::ortamdan());
        $this->assertFalse((bool)r2ResimEtkin());
        $this->assertFalse((bool)r2YedekEtkin());
    }

    public function testHesapKimligindenEndpointUretilir(): void
    {
        $this->ortam(['R2_ACCESS_KEY_ID' => 'AK', 'R2_SECRET_ACCESS_KEY' => 'SK', 'R2_ENDPOINT' => null, 'R2_ACCOUNT_ID' => 'abc123']);
        $url = R2Istemci::ortamdan()->sureliBaglanti('kova', 'x', 60);
        $this->assertStringStartsWith('https://abc123.r2.cloudflarestorage.com/kova/x?', $url);
    }

    public function testGenelAdresVeAnahtarCozumleme(): void
    {
        $this->ortam(['R2_PUBLIC_URL' => 'https://cdn.example.test/']);
        $adres = r2GenelAdres('products/abc123.jpg');
        $this->assertSame('https://cdn.example.test/products/abc123.jpg', $adres);
        $this->assertSame('products/abc123.jpg', r2AnahtarCoz($adres));
        // Başka alan adı, dizin geçişi veya izinsiz klasör silinemez
        $this->assertNull(r2AnahtarCoz('https://kotu.example/products/abc.jpg'));
        $this->assertNull(r2AnahtarCoz('https://cdn.example.test/products/../yedek.sql.gz'));
        $this->assertNull(r2AnahtarCoz('https://cdn.example.test/veritabani/yedek.sql.gz'));
        $this->assertNull(r2AnahtarCoz('uploads/products/abc.jpg'));
    }
}

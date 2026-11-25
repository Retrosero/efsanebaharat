<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

/** HTTP düzeyinde güvenlik kontrolleri */
final class GuvenlikHttpTest extends IntegrationTestCase
{
    public function testBaskaSitedenGelenPostReddedilir(): void
    {
        $this->girisYap();
        $urun = self::$fixture['adet_urun'];
        $stok = self::urunStok($urun);
        $r = $this->istek('POST', 'satis_kaydet.php', json_encode([
            'musteri_id' => self::$fixture['musteri'],
            'items' => [['id' => $urun, 'qty' => 1, 'price' => 0, 'olcumBirimi' => 'adet']],
        ]), ['Origin: https://kotu-site.example', 'Content-Type: application/json']);
        $this->assertSame(403, $r['status']);
        $this->assertSame($stok, self::urunStok($urun));
    }

    public function testGetIleSilmeYapilamaz(): void
    {
        $this->girisYap();
        $r = $this->get('ajax_islem.php?islem=fatura_sil&id=1');
        $this->assertSame(405, $r['status']);
    }

    public function testKullaniciSilmeTokenGerektirir(): void
    {
        $this->girisYap();
        $id = (int)self::$pdo->query("SELECT id FROM kullanicilar WHERE eposta = '" . self::CALISAN_EPOSTA . "'")->fetchColumn();
        $this->get("kullanicilar.php?sil=$id");
        $sayi = (int)self::$pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE id = $id")->fetchColumn();
        $this->assertSame(1, $sayi, 'CSRF token olmadan kullanıcı silinmemeli');
    }

    public static function gizliDosyalar(): array
    {
        return [
            ['includes/db.php'], ['includes/auth.php'], ['efsanebaharat.sql'], ['db-init/01-init.sql'],
            ['README.md'], ['composer.json'], ['.env.example'], ['Dockerfile'], ['.git/config'], ['logs/'],
            ['sutun_ekle.php'], ['yeni_kullanici_ekle.php'],
        ];
    }

    #[DataProvider('gizliDosyalar')]
    public function testHassasDosyalarSunulmaz(string $yol): void
    {
        $r = $this->get($yol);
        $this->assertContains($r['status'], [403, 404], "$yol erişilebilir olmamalı (HTTP {$r['status']})");
    }

    public function testGuvenlikBasliklariGonderilir(): void
    {
        $r = $this->get('giris.php');
        $this->assertSame('SAMEORIGIN', $r['headers']['x-frame-options'] ?? null);
        $this->assertSame('nosniff', $r['headers']['x-content-type-options'] ?? null);
        $this->assertArrayNotHasKey('x-powered-by', $r['headers']);
        $cerez = $r['headers']['set-cookie'] ?? '';
        $this->assertStringContainsStringIgnoringCase('httponly', $cerez);
        $this->assertStringContainsStringIgnoringCase('samesite=lax', $cerez);
    }

    public function testCalisanKurulumBetiginiCalistiramaz(): void
    {
        $this->girisYap(self::CALISAN_EPOSTA);
        foreach (['kurulum.php', 'create_token_columns.php', 'kullanici_tablosu_guncelle.php', 'stok_kg_ekle.php'] as $yol) {
            $r = $this->get($yol);
            $this->assertNotSame(200, $r['status'], "$yol çalışan rolüne açık olmamalı");
        }
    }

    public function testCalisanYetkisizUcNoktayaErisemez(): void
    {
        // Çalışan rolünün ürünler ekranı yetkisini kaldır
        $sayfa = self::$pdo->query("SELECT id FROM sayfalar WHERE sayfa_url = 'urunler.php'")->fetchColumn();
        if (!$sayfa) {
            $this->markTestSkipped('sayfalar tablosunda urunler.php yok');
        }
        self::$pdo->prepare('DELETE FROM rol_sayfa_izinleri WHERE rol_id = 2 AND sayfa_id = ?')->execute([$sayfa]);

        $this->girisYap(self::CALISAN_EPOSTA);
        $r = $this->istek('POST', 'urun_sil.php', ['id' => self::$fixture['adet_urun']], ['Accept: application/json']);
        $this->assertSame(403, $r['status']);
        $var = (int)self::$pdo->query('SELECT COUNT(*) FROM urunler WHERE id = ' . self::$fixture['adet_urun'])->fetchColumn();
        $this->assertSame(1, $var);
    }

    public function testUploadsKlasorundePhpCalismaz(): void
    {
        // Uygulama konteyneriyle paylaşılan dizin olmadığından yalnızca HTTP davranışını kontrol ediyoruz:
        // uploads altında var olmayan bir .php isteği 403/404 olmalı, asla 200 olmamalı.
        $r = $this->get('uploads/products/x.php');
        $this->assertContains($r['status'], [403, 404]);
    }
}

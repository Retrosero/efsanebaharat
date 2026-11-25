<?php

namespace Tests\Integration;

/** satis_kaydet.php: birim dönüşümleri, stok, cari ve doğrulama */
final class SatisTest extends IntegrationTestCase
{
    private function satis(array $items, float $iskonto = 0): array
    {
        $r = $this->postJson('satis_kaydet.php', [
            'musteri_id' => self::$fixture['musteri'],
            'items' => $items,
            'discountRate' => $iskonto,
            'note' => 'test',
            'saleDate' => date('Y-m-d'),
        ]);
        $json = json_decode($r['body'], true);
        $this->assertIsArray($json, 'JSON bekleniyordu: ' . substr($r['body'], 0, 300));
        return $json;
    }

    private function detaylar(int $faturaId): array
    {
        $s = self::$pdo->prepare('SELECT * FROM fatura_detaylari WHERE fatura_id = ? ORDER BY id');
        $s->execute([$faturaId]);
        return $s->fetchAll();
    }

    private function cari(): float
    {
        $s = self::$pdo->prepare('SELECT cari_bakiye FROM musteriler WHERE id = ?');
        $s->execute([self::$fixture['musteri']]);
        return (float)$s->fetchColumn();
    }

    public function testKiloUrunuGramlaSatilinca(): void
    {
        $this->girisYap();
        $urun = self::$fixture['kg_urun'];
        $stok = self::urunStok($urun);
        $cari = $this->cari();

        // Sepet: 250 gr x 0.5 TL/gr (500 TL/kg)
        $yanit = $this->satis([['id' => $urun, 'qty' => 250, 'price' => 0.5, 'olcumBirimi' => 'gr']]);
        $this->assertTrue($yanit['success'], $yanit['message'] ?? '');

        $d = $this->detaylar((int)$yanit['fatura_id'])[0];
        $this->assertSame('kg', $d['olcum_birimi']);
        $this->assertEqualsWithDelta(0.25, (float)$d['miktar'], 1e-6);
        $this->assertEqualsWithDelta(500, (float)$d['birim_fiyat'], 1e-6);
        $this->assertEqualsWithDelta(125, (float)$d['toplam_fiyat'], 1e-6, 'satır toplamı 250 gr x 0.5 = 125 olmalı');
        $this->assertEqualsWithDelta($stok - 0.25, self::urunStok($urun), 1e-6);
        $this->assertEqualsWithDelta($cari + 125, $this->cari(), 1e-6);

        $f = self::$pdo->query('SELECT toplam_tutar, genel_toplam FROM faturalar WHERE id = ' . (int)$yanit['fatura_id'])->fetch();
        $this->assertEqualsWithDelta(125, (float)$f['toplam_tutar'], 1e-6);
    }

    public function testGramUrunuKiloylaSatilinca(): void
    {
        $this->girisYap();
        $urun = self::$fixture['gr_urun'];
        $stok = self::urunStok($urun);

        $yanit = $this->satis([['id' => $urun, 'qty' => 1.2, 'price' => 400, 'olcumBirimi' => 'kg']], 10);
        $this->assertTrue($yanit['success'], $yanit['message'] ?? '');

        $d = $this->detaylar((int)$yanit['fatura_id'])[0];
        $this->assertSame('gr', $d['olcum_birimi']);
        $this->assertEqualsWithDelta(1200, (float)$d['miktar'], 1e-6);
        $this->assertEqualsWithDelta(480, (float)$d['toplam_fiyat'], 1e-6);
        $this->assertEqualsWithDelta(48, (float)$d['indirim_tutari'], 1e-6);
        $this->assertEqualsWithDelta($stok - 1200, self::urunStok($urun), 1e-6);
    }

    public function testAyniUrunIkiBirimdeSatilincaStokToplamKontrolEdilir(): void
    {
        $this->girisYap();
        $urun = self::$fixture['kg_urun'];
        $stok = self::urunStok($urun);
        // Toplam stok + 1 gr isteniyor: tek tek yeterli, birlikte yetersiz
        $yanit = $this->satis([
            ['id' => $urun, 'qty' => $stok, 'price' => 500, 'olcumBirimi' => 'kg'],
            ['id' => $urun, 'qty' => 1, 'price' => 0.5, 'olcumBirimi' => 'gr'],
        ]);
        $this->assertFalse($yanit['success']);
        $this->assertStringContainsString('Yetersiz stok', $yanit['message']);
        $this->assertEqualsWithDelta($stok, self::urunStok($urun), 1e-6);
    }

    public function testNegatifMiktarStokArtiramaz(): void
    {
        $this->girisYap();
        $urun = self::$fixture['adet_urun'];
        $stok = self::urunStok($urun);
        $cari = $this->cari();
        $yanit = $this->satis([['id' => $urun, 'qty' => -100, 'price' => 25, 'olcumBirimi' => 'adet']]);
        $this->assertFalse($yanit['success']);
        $this->assertSame($stok, self::urunStok($urun));
        $this->assertEqualsWithDelta($cari, $this->cari(), 1e-6);
    }

    public function testGecersizIskontoSinirlanir(): void
    {
        $this->girisYap();
        $yanit = $this->satis([['id' => self::$fixture['gr_urun'], 'qty' => 10, 'price' => 0.4, 'olcumBirimi' => 'gr']], 500);
        $this->assertTrue($yanit['success'], $yanit['message'] ?? '');
        $f = self::$pdo->query('SELECT genel_toplam FROM faturalar WHERE id = ' . (int)$yanit['fatura_id'])->fetch();
        $this->assertGreaterThanOrEqual(0, (float)$f['genel_toplam'], 'iskonto %100 ile sınırlanmalı, negatif toplam olmamalı');
    }

    public function testBilinmeyenMusteriVeUrunReddedilir(): void
    {
        $this->girisYap();
        $r = $this->postJson('satis_kaydet.php', ['musteri_id' => 999999999, 'items' => [['id' => self::$fixture['adet_urun'], 'qty' => 1, 'price' => 1, 'olcumBirimi' => 'adet']]]);
        $this->assertFalse(json_decode($r['body'], true)['success']);
        $yanit = $this->satis([['id' => 999999999, 'qty' => 1, 'price' => 1, 'olcumBirimi' => 'adet']]);
        $this->assertFalse($yanit['success']);
        $yanit = $this->satis([]);
        $this->assertFalse($yanit['success']);
    }

    public function testAdetUrunuStoktanFazlaSatilamaz(): void
    {
        $this->girisYap();
        $urun = self::$fixture['adet_urun'];
        $stok = self::urunStok($urun);
        $yanit = $this->satis([['id' => $urun, 'qty' => $stok + 1, 'price' => 25, 'olcumBirimi' => 'adet']]);
        $this->assertFalse($yanit['success']);
        $this->assertSame($stok, self::urunStok($urun));
    }
}

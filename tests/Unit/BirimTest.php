<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BirimTest extends TestCase
{
    public static function miktarProvider(): array
    {
        return [
            'gr -> kg' => [250, 'gr', 'kg', 0.25],
            'kg -> gr' => [1.5, 'kg', 'gr', 1500],
            'kg -> kg' => [2, 'kg', 'kg', 2],
            'adet aynen' => [3, 'adet', 'kg', 3],
        ];
    }

    #[DataProvider('miktarProvider')]
    public function testMiktarCevir($miktar, $kaynak, $hedef, $beklenen): void
    {
        $this->assertEqualsWithDelta($beklenen, birimMiktarCevir($miktar, $kaynak, $hedef), 1e-9);
    }

    public function testBirimFiyatCevir(): void
    {
        // 500 TL/kg ürün gramla satılınca 0.5 TL/gr
        $this->assertEqualsWithDelta(0.5, birimFiyatCevir(500, 'kg', 'gr'), 1e-9);
        // 0.4 TL/gr ürün kiloyla satılınca 400 TL/kg
        $this->assertEqualsWithDelta(400, birimFiyatCevir(0.4, 'gr', 'kg'), 1e-9);
        $this->assertSame(12.0, birimFiyatCevir(12, 'adet', 'adet'));
    }

    public function testGramSatisiKiloUrunundeDogruNormalizeEdilir(): void
    {
        // Sepet: 250 gr x 0.5 TL/gr (ürün kg cinsinden, 500 TL/kg)
        $sonuc = satisKalemiHesapla(['qty' => 250, 'price' => 0.5, 'olcumBirimi' => 'gr'], 'kg');
        $this->assertEqualsWithDelta(0.25, $sonuc['miktar'], 1e-9);      // stoktan 0.25 kg düşer
        $this->assertEqualsWithDelta(500, $sonuc['birim_fiyat'], 1e-9);  // kg fiyatı saklanır
        $this->assertEqualsWithDelta(125, $sonuc['toplam'], 1e-9);       // 250 * 0.5
        // Tutarlılık: miktar x birim fiyat = toplam
        $this->assertEqualsWithDelta($sonuc['toplam'], $sonuc['miktar'] * $sonuc['birim_fiyat'], 1e-6);
    }

    public function testKiloSatisiGramUrunundeDogruNormalizeEdilir(): void
    {
        $sonuc = satisKalemiHesapla(['qty' => 1.2, 'price' => 400, 'olcumBirimi' => 'kg'], 'gr');
        $this->assertEqualsWithDelta(1200, $sonuc['miktar'], 1e-9);
        $this->assertEqualsWithDelta(0.4, $sonuc['birim_fiyat'], 1e-9);
        $this->assertEqualsWithDelta(480, $sonuc['toplam'], 1e-9);
    }

    public function testAdetUrunu(): void
    {
        $sonuc = satisKalemiHesapla(['qty' => 3, 'price' => 10, 'olcumBirimi' => 'adet'], 'adet');
        $this->assertSame(3.0, $sonuc['miktar']);
        $this->assertEqualsWithDelta(30, $sonuc['toplam'], 1e-9);
    }

    public static function gecersizKalemProvider(): array
    {
        return [
            'negatif miktar (stok artırma saldırısı)' => [['qty' => -5, 'price' => 10, 'olcumBirimi' => 'adet'], 'adet'],
            'sıfır miktar' => [['qty' => 0, 'price' => 10, 'olcumBirimi' => 'adet'], 'adet'],
            'negatif fiyat' => [['qty' => 1, 'price' => -10, 'olcumBirimi' => 'adet'], 'adet'],
            'sayı olmayan miktar' => [['qty' => 'abc', 'price' => 10, 'olcumBirimi' => 'adet'], 'adet'],
            'bilinmeyen birim' => [['qty' => 1, 'price' => 10, 'olcumBirimi' => 'litre'], 'adet'],
            'adet ürünü gramla' => [['qty' => 100, 'price' => 1, 'olcumBirimi' => 'gr'], 'adet'],
            'kg ürünü adetle' => [['qty' => 1, 'price' => 1, 'olcumBirimi' => 'adet'], 'kg'],
            'sonsuz miktar' => [['qty' => INF, 'price' => 1, 'olcumBirimi' => 'kg'], 'kg'],
        ];
    }

    #[DataProvider('gecersizKalemProvider')]
    public function testGecersizKalemlerReddedilir(array $item, string $urunBirimi): void
    {
        $this->expectException(InvalidArgumentException::class);
        satisKalemiHesapla($item, $urunBirimi);
    }
}

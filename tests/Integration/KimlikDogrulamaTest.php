<?php

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

final class KimlikDogrulamaTest extends IntegrationTestCase
{
    public static function korunanSayfalar(): array
    {
        $sayfalar = [
            // Daha önce config.php herkese otomatik yönetici oturumu açıyordu
            'index.php', 'urunleri_disa_aktar_xml.php',
            // Modül ekranları
            'satis.php', 'alis.php', 'alis_faturalari.php', 'ayarlar.php', 'gunsonu.php', 'kullanicilar.php',
            'kullanici_ekle.php', 'musteriler.php', 'musteri_ekle.php', 'musteri_tipleri.php', 'onay_merkezi.php',
            'raporlar.php', 'roller.php', 'tahsilat.php', 'tediye.php', 'tediye_makbuzlari.php', 'urunler.php',
            'urun_ekle.php', 'urun_detay.php?id=1', 'musteri_detay.php?id=1', 'fatura_detay.php?id=1',
            // Daha önce giriş kontrolü olmayan uç noktalar
            'ajax_islem.php?islem=musteri_bakiye&id=1', 'filter_products.php', 'guncelbakiye.php',
            'get_islem_detay.php?id=1', 'ajax/gunsonu_verileri.php', 'musteri_guncelle.php', 'urun_sil.php',
            'satis_kaydet.php', 'kurulum.php', 'create_token_columns.php', 'kullanici_tablosu_guncelle.php',
            'stok_kg_ekle.php',
        ];
        return array_combine($sayfalar, array_map(fn($s) => [$s], $sayfalar));
    }

    #[DataProvider('korunanSayfalar')]
    public function testGirisYapmadanErisilemez(string $yol): void
    {
        $this->assertGirisSayfasinaYonlendirir($this->get($yol), $yol);
    }

    public function testGirisYapmadanPostIslemleriCalismaz(): void
    {
        $oncekiStok = self::urunStok(self::$fixture['adet_urun']);
        $r = $this->postJson('satis_kaydet.php', [
            'musteri_id' => self::$fixture['musteri'],
            'items' => [['id' => self::$fixture['adet_urun'], 'qty' => 1, 'price' => 1, 'olcumBirimi' => 'adet']],
        ]);
        $this->assertSame(401, $r['status']);
        $this->assertSame($oncekiStok, self::urunStok(self::$fixture['adet_urun']));

        $r = $this->istek('POST', 'urun_sil.php', ['id' => self::$fixture['adet_urun']], ['Accept: application/json']);
        $this->assertSame(401, $r['status']);
        $this->assertGreaterThan(0, self::urunStok(self::$fixture['adet_urun']) + 1, 'ürün silinmemeli');
        $var = self::$pdo->prepare('SELECT COUNT(*) FROM urunler WHERE id = ?');
        $var->execute([self::$fixture['adet_urun']]);
        $this->assertSame(1, (int)$var->fetchColumn());
    }

    public function testGirisSayfasiAcilir(): void
    {
        $r = $this->get('giris.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('name="eposta"', $r['body']);
    }

    public function testYanlisParolaIleGirisYapilamaz(): void
    {
        $r = $this->istek('POST', 'giris.php', ['eposta' => self::ADMIN_EPOSTA, 'sifre' => 'yanlis-parola']);
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('Geçersiz e-posta veya şifre', $r['body']);
        $this->assertGirisSayfasinaYonlendirir($this->get('index.php'), 'index.php');
    }

    public function testGirisSonrasiAnaSayfaAcilirVeCikisOturumuKapatir(): void
    {
        $this->girisYap();
        $r = $this->get('index.php');
        $this->assertSame(200, $r['status']);

        $token = self::$pdo->query("SELECT remember_token FROM kullanicilar WHERE eposta = '" . self::ADMIN_EPOSTA . "'")->fetchColumn();
        $this->assertNotEmpty($token, 'beni hatırla token oluşturulmalı');

        $this->get('cikis.php');
        $this->assertGirisSayfasinaYonlendirir($this->get('index.php'), 'index.php (çıkış sonrası)');
        $token = self::$pdo->query("SELECT remember_token FROM kullanicilar WHERE eposta = '" . self::ADMIN_EPOSTA . "'")->fetchColumn();
        $this->assertNull($token, 'çıkışta beni hatırla token iptal edilmeli');
    }

    public function testPasifKullanicininOturumuSonlanir(): void
    {
        $this->girisYap(self::CALISAN_EPOSTA);
        self::$pdo->prepare('UPDATE kullanicilar SET aktif = 0 WHERE eposta = ?')->execute([self::CALISAN_EPOSTA]);
        try {
            $this->assertGirisSayfasinaYonlendirir($this->get('satis.php'), 'satis.php (pasif kullanıcı)');
        } finally {
            self::$pdo->prepare('UPDATE kullanicilar SET aktif = 1 WHERE eposta = ?')->execute([self::CALISAN_EPOSTA]);
        }
    }

    public function testSahteBeniHatirlaCereziKabulEdilmez(): void
    {
        $r = $this->get('index.php', ['Cookie: remember_token=1:sahte-token']);
        $this->assertGirisSayfasinaYonlendirir($r, 'index.php (sahte çerez)');
        $r = $this->get('index.php', ['Cookie: remember_token=bozukdeger']);
        $this->assertGirisSayfasinaYonlendirir($r, 'index.php (bozuk çerez)');
    }
}

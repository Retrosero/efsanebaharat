<?php

namespace Tests\Integration;

/**
 * Tüm modül ekranlarının yönetici oturumuyla hatasız açıldığını doğrular
 * (HTTP 200, PHP hata/uyarı çıktısı yok).
 */
final class ModulSayfalariTest extends IntegrationTestCase
{
    private static function ilkId(string $sql): ?int
    {
        $id = self::$pdo->query($sql)->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    private function sayfalar(): array
    {
        $musteri = self::$fixture['musteri'];
        $urun = self::$fixture['kg_urun'];
        $satisFatura = self::ilkId("SELECT id FROM faturalar WHERE fatura_turu = 'satis' ORDER BY id DESC LIMIT 1");
        $alisFatura = self::ilkId("SELECT id FROM faturalar WHERE fatura_turu = 'alis' ORDER BY id DESC LIMIT 1");
        $tahsilat = self::ilkId("SELECT id FROM odeme_tahsilat WHERE islem_turu = 'tahsilat' ORDER BY id DESC LIMIT 1");
        $tediye = self::ilkId("SELECT id FROM odeme_tahsilat WHERE islem_turu = 'tediye' ORDER BY id DESC LIMIT 1");
        $kullanici = self::ilkId("SELECT id FROM kullanicilar WHERE eposta = '" . self::CALISAN_EPOSTA . "'");

        $sayfalar = [
            'Ana sayfa' => 'index.php',
            'Satış' => 'satis.php',
            'Alış' => 'alis.php',
            'Alış faturaları' => 'alis_faturalari.php',
            'Ayarlar' => 'ayarlar.php',
            'Gün sonu' => 'gunsonu.php',
            'Kullanıcılar' => 'kullanicilar.php',
            'Kullanıcı ekle' => 'kullanici_ekle.php',
            'Kullanıcı düzenle' => "kullanici_duzenle.php?id=$kullanici",
            'Müşteriler' => 'musteriler.php',
            'Müşteri ekle' => 'musteri_ekle.php',
            'Müşteri detay' => "musteri_detay.php?id=$musteri",
            'Müşteri tipleri' => 'musteri_tipleri.php',
            'Onay merkezi' => 'onay_merkezi.php',
            'Raporlar' => 'raporlar.php',
            'Roller' => 'roller.php',
            'Tahsilat' => 'tahsilat.php',
            'Tediye' => 'tediye.php',
            'Tediye makbuzları' => 'tediye_makbuzlari.php',
            'Ürünler' => 'urunler.php',
            'Ürünler (kritik stok)' => 'urunler.php?kritik=1',
            'Ürün ekle' => 'urun_ekle.php',
            'Ürün detay' => "urun_detay.php?id=$urun",
            'Ürün düzenle' => "urun_duzenle.php?id=$urun",
            'Ürün XML dışa aktar' => 'urunleri_disa_aktar_xml.php',
            'Stok kg ekle (yönetici)' => 'stok_kg_ekle.php',
            'Müşteri bakiye (AJAX)' => "ajax_islem.php?islem=musteri_bakiye&id=$musteri",
            'Ürün filtre (AJAX)' => 'filter_products.php',
            'Gün sonu verileri (AJAX)' => 'ajax/gunsonu_verileri.php',
        ];
        if ($satisFatura) {
            $sayfalar['Satış faturası detay'] = "fatura_detay.php?id=$satisFatura";
            $sayfalar['Satış faturası düzenle'] = "fatura_duzenle.php?id=$satisFatura";
        }
        if ($alisFatura) {
            $sayfalar['Alış faturası detay'] = "fatura_detay.php?id=$alisFatura";
        }
        if ($tahsilat) {
            $sayfalar['Tahsilat detay'] = "tahsilat_detay.php?id=$tahsilat";
            $sayfalar['Tahsilat düzenle'] = "tahsilat_duzenle.php?id=$tahsilat";
        }
        if ($tediye) {
            $sayfalar['Tediye makbuzu'] = "tediye_makbuz.php?id=$tediye";
        }
        return $sayfalar;
    }

    public function testTumModulSayfalariHatasizAcilir(): void
    {
        $this->girisYap();
        $hatalar = [];
        foreach ($this->sayfalar() as $ad => $yol) {
            $r = $this->get($yol);
            if ($r['status'] !== 200) {
                $hatalar[] = "$ad ($yol): HTTP {$r['status']} " . ($r['headers']['location'] ?? '');
                continue;
            }
            if (preg_match('/(Fatal error|Parse error|Uncaught|Warning<\/b>:|Notice<\/b>:|Deprecated<\/b>:|SQLSTATE\[)/', $r['body'], $m)) {
                $i = strpos($r['body'], $m[0]);
                $hatalar[] = "$ad ($yol): " . trim(strip_tags(substr($r['body'], $i, 300)));
            }
        }
        $this->assertSame([], $hatalar, "Hatalı sayfalar:\n" . implode("\n", $hatalar));
    }

    public function testMusteriBakiyeAjaxJsonDoner(): void
    {
        $this->girisYap();
        $r = $this->get('ajax_islem.php?islem=musteri_bakiye&id=' . self::$fixture['musteri']);
        $json = json_decode($r['body'], true);
        $this->assertIsArray($json, $r['body']);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('try_bakiye', $json);
    }

    public function testXmlDisaAktarimGecerliXmlUretir(): void
    {
        $this->girisYap();
        $r = $this->get('urunleri_disa_aktar_xml.php');
        $this->assertSame(200, $r['status']);
        libxml_use_internal_errors(true);
        $this->assertNotFalse(simplexml_load_string($r['body']), 'XML ayrıştırılamadı: ' . substr($r['body'], 0, 200));
    }

    public function testSolMenuKategorilereAyrilmistir(): void
    {
        $this->girisYap();
        $r = $this->get('satis.php');
        $this->assertSame(200, $r['status']);
        $govde = $r['body'];
        $sira = [];
        foreach (['Genel', 'Satış', 'Alış', 'Stok', 'Cari', 'Raporlar', 'Yönetim'] as $kategori) {
            $konum = strpos($govde, '>' . $kategori . '</div>');
            $this->assertNotFalse($konum, "Menüde '$kategori' kategorisi yok");
            $sira[] = $konum;
        }
        $siraliKopya = $sira;
        sort($siraliKopya);
        $this->assertSame($siraliKopya, $sira, 'Kategoriler beklenen sırada değil');
        // Raporlar bağlantısı artık gerçek sayfaya gider, aktif sayfa işaretlenir
        $this->assertStringContainsString('href="raporlar.php"', $govde);
        $this->assertMatchesRegularExpression('#<a href="satis\.php"[^>]*aria-current="page"#s', $govde);
    }

    public function testCalisanYetkisizKategorileriGormez(): void
    {
        // Çalışan rolünden tüm sayfa izinlerini kaldır: yalnızca yetkili öğeler/kategoriler görünmeli
        self::$pdo->exec('DELETE FROM rol_sayfa_izinleri WHERE rol_id = 2');
        $sayfa = self::$pdo->query("SELECT id FROM sayfalar WHERE sayfa_url = 'index.php'")->fetchColumn();
        self::$pdo->prepare('INSERT INTO rol_sayfa_izinleri (rol_id, sayfa_id, izin) VALUES (2, ?, 1)')->execute([$sayfa]);

        $this->girisYap(self::CALISAN_EPOSTA);
        $r = $this->get('index.php');
        $this->assertSame(200, $r['status']);
        $this->assertStringContainsString('>Genel</div>', $r['body']);
        $this->assertStringNotContainsString('>Yönetim</div>', $r['body']);
        $this->assertStringNotContainsString('href="kullanicilar.php"', $r['body']);
    }

    public function testFaturaDisaAktarmaDemoDegil(): void
    {
        $id = self::$pdo->query("SELECT f.id FROM faturalar f JOIN fatura_detaylari d ON d.fatura_id = f.id ORDER BY f.id DESC LIMIT 1")->fetchColumn();
        if (!$id) {
            $this->markTestSkipped('Detaylı fatura yok');
        }
        $this->girisYap();
        $r = $this->get("fatura_detay.php?id=$id");
        $this->assertSame(200, $r['status']);
        $this->assertStringNotContainsString('(demo)', $r['body']);
        $this->assertStringContainsString('assets/js/fatura_disa_aktar.js', $r['body']);
        $this->assertMatchesRegularExpression('/const FATURA_VERISI = (\{.*?\});\r?\n/s', $r['body']);
        preg_match('/const FATURA_VERISI = (\{.*?\});\r?\n/s', $r['body'], $m);
        $veri = json_decode($m[1], true);
        $this->assertIsArray($veri, 'FATURA_VERISI geçerli JSON olmalı');
        $this->assertNotEmpty($veri['kalemler']);
        $this->assertArrayHasKey('genelToplam', $veri);
        $js = $this->get('assets/js/fatura_disa_aktar.js');
        $this->assertSame(200, $js['status']);
    }
}

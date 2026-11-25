<?php

namespace Tests\Integration;

use R2Istemci;

/**
 * Cloudflare R2 entegrasyonu (testte MinIO): resim yükleme/silme, mevcut resimlerin taşınması
 * ve veritabanı yedekleri.
 */
final class R2EntegrasyonTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!getenv('R2_ENDPOINT')) {
            $this->markTestSkipped('R2_ENDPOINT tanımlı değil');
        }
    }

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    private function adresGetir(string $url): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
        $govde = curl_exec($ch);
        $durum = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $tur = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        return ['status' => $durum, 'body' => (string)$govde, 'type' => (string)$tur];
    }

    private function urunResmi(int $id): ?string
    {
        $s = self::$pdo->prepare('SELECT resim_url FROM urunler WHERE id = ?');
        $s->execute([$id]);
        return $s->fetchColumn() ?: null;
    }

    public function testUrunResmiR2yeYuklenirVeSilinir(): void
    {
        $this->girisYap();
        $urun = self::$fixture['kg_urun'];
        $tmp = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($tmp, $this->png());

        $r = $this->istek('POST', "urun_duzenle.php?id=$urun", [
            'urun_adi' => 'Test Karabiber (kg)', 'kdv_orani' => '0', 'birim_fiyat' => '500', 'stok_miktari' => '10',
            'resim_1' => new \CURLFile($tmp, 'image/png', 'urun.png'),
        ]);
        @unlink($tmp);
        preg_match('/(Güncelleme hatası[^<]*|Fatal error[^<]*|Warning[^<]*)/u', $r['body'], $hata);
        $this->assertSame(302, $r['status'], $hata[0] ?? 'kaydetme yönlendirmedi');

        $adres = $this->urunResmi($urun);
        $this->assertNotNull($adres);
        $this->assertStringStartsWith(getenv('R2_PUBLIC_URL') . '/products/', $adres, 'resim R2 adresiyle kaydedilmeli');

        $resim = $this->adresGetir($adres);
        $this->assertSame(200, $resim['status'], 'R2 adresi herkese açık okunabilmeli');
        $this->assertSame('image/png', $resim['type']);
        $this->assertSame($this->png(), $resim['body']);

        // Resmi sil: R2'den de kaldırılmalı
        $r = $this->istek('POST', 'ajax/delete_product_image.php', json_encode(['urun_id' => $urun, 'resim_index' => 0]), ['Content-Type: application/json']);
        $this->assertStringContainsString('"success":true', $r['body']);
        $this->assertNull($this->urunResmi($urun));
        $this->assertNotSame(200, $this->adresGetir($adres)['status'], 'silinen resim R2de kalmamalı');
    }

    public function testResimOlmayanDosyaR2yeYuklenmez(): void
    {
        $this->girisYap();
        $urun = self::$fixture['gr_urun'];
        $once = $this->urunResmi($urun);
        $tmp = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($tmp, '<?php echo 1;');
        $this->istek('POST', "urun_duzenle.php?id=$urun", [
            'urun_adi' => 'Test Safran (gr)', 'kdv_orani' => '0', 'birim_fiyat' => '0,4', 'stok_miktari' => '5000',
            'resim_1' => new \CURLFile($tmp, 'image/png', 'shell.php'),
        ]);
        @unlink($tmp);
        $this->assertSame($once, $this->urunResmi($urun));
    }

    public function testMevcutResimlerR2yeTasinir(): void
    {
        $kok = realpath(__DIR__ . '/../..');
        $goreli = 'uploads/products/tasima_testi_' . bin2hex(random_bytes(4)) . '.png';
        @mkdir(dirname("$kok/$goreli"), 0777, true);
        file_put_contents("$kok/$goreli", $this->png());
        $urun = self::$fixture['adet_urun'];
        self::$pdo->prepare('UPDATE urunler SET resim_url = ?, resim_url_1 = ? WHERE id = ?')
            ->execute([$goreli, 'uploads/products/olmayan_dosya.png', $urun]);

        try {
            // Kuru çalışma hiçbir şeyi değiştirmez
            exec('php ' . escapeshellarg("$kok/cron/resimleri_r2ye_tasi.php") . ' --kuru 2>&1', $cikti, $kod);
            $this->assertSame(0, $kod, implode("\n", $cikti));
            $this->assertSame($goreli, $this->urunResmi($urun));

            exec('php ' . escapeshellarg("$kok/cron/resimleri_r2ye_tasi.php") . ' 2>&1', $cikti, $kod);
            $this->assertSame(0, $kod, implode("\n", $cikti));
            $yeni = $this->urunResmi($urun);
            $this->assertStringStartsWith(getenv('R2_PUBLIC_URL') . '/products/', $yeni);
            $this->assertSame($this->png(), $this->adresGetir($yeni)['body']);

            $s = self::$pdo->prepare('SELECT resim_url_1 FROM urunler WHERE id = ?');
            $s->execute([$urun]);
            $this->assertSame('uploads/products/olmayan_dosya.png', $s->fetchColumn(), 'bulunamayan resim olduğu gibi kalmalı');

            // Tekrar çalıştırmak güvenli: aynı adres korunur
            exec('php ' . escapeshellarg("$kok/cron/resimleri_r2ye_tasi.php") . ' 2>&1', $cikti, $kod);
            $this->assertSame($yeni, $this->urunResmi($urun));
        } finally {
            @unlink("$kok/$goreli");
            self::$pdo->prepare('UPDATE urunler SET resim_url = NULL, resim_url_1 = NULL WHERE id = ?')->execute([$urun]);
        }
    }

    public function testYoneticiYedekAlirListelerVeIndirir(): void
    {
        $this->girisYap();
        $r = $this->istek('POST', 'yedekler.php', ['islem' => 'yedek_al']);
        $this->assertSame(302, $r['status']);

        $sayfa = $this->get('yedekler.php');
        $this->assertSame(200, $sayfa['status']);
        $this->assertStringContainsString('Yedek alındı', $sayfa['body']);
        $this->assertMatchesRegularExpression('#yedekler\.php\?indir=(veritabani%2F[^"]+\.sql\.gz)#', $sayfa['body']);
        preg_match('#yedekler\.php\?indir=(veritabani%2F[^"]+\.sql\.gz)#', $sayfa['body'], $m);

        $indir = $this->get('yedekler.php?indir=' . $m[1]);
        $this->assertSame(302, $indir['status']);
        $dosya = $this->adresGetir($indir['headers']['location']);
        $this->assertSame(200, $dosya['status'], 'süreli bağlantı çalışmalı');
        $sql = gzdecode($dosya['body']);
        $this->assertNotFalse($sql, 'yedek gzip olmalı');
        $this->assertStringContainsString('CREATE TABLE `urunler`', $sql);
        $this->assertStringContainsString('INSERT INTO `urunler`', $sql);
        $this->assertStringContainsString('Test Karabiber (kg)', $sql);
        $this->assertStringContainsString('CREATE TRIGGER', $sql);

        // Yedek kovası herkese açık değil
        $anahtar = rawurldecode($m[1]);
        $acik = $this->adresGetir('http://minio:9000/' . getenv('R2_BACKUP_BUCKET') . '/' . $anahtar);
        $this->assertSame(403, $acik['status'], 'yedekler imzasız indirilememeli');
    }

    public function testCronYedekBetigiCalisir(): void
    {
        $kok = realpath(__DIR__ . '/../..');
        exec('php ' . escapeshellarg("$kok/cron/veritabani_yedekle.php") . ' 2>&1', $cikti, $kod);
        $this->assertSame(0, $kod, implode("\n", $cikti));
        $this->assertStringContainsString('Yedek alındı: veritabani/', implode("\n", $cikti));
    }

    public function testSuresiDolanYedeklerSilinir(): void
    {
        require_once __DIR__ . '/../../includes/yedek.php';
        $istemci = R2Istemci::ortamdan();
        $kova = getenv('R2_BACKUP_BUCKET');
        $eski = 'veritabani/2000/01/eski_2000-01-01_000000.sql.gz';
        $istemci->yukle($kova, $eski, 'x', 'application/gzip');
        // 0 gün saklama: yeni alınan hariç tüm eski yedekler silinir
        $sonuc = veritabaniYedekle(self::$pdo, 0);
        $anahtarlar = array_column($istemci->listele($kova, 'veritabani/'), 'anahtar');
        $this->assertNotContains($eski, $anahtarlar);
        $this->assertContains($sonuc['anahtar'], $anahtarlar, 'yeni yedek silinmemeli');
    }

    public function testCalisanYedeklereErisemezVeGecersizAnahtarReddedilir(): void
    {
        $this->girisYap(self::CALISAN_EPOSTA);
        $this->assertNotSame(200, $this->get('yedekler.php')['status']);
        $r = $this->istek('POST', 'yedekler.php', ['islem' => 'yedek_al']);
        $this->assertNotSame(200, $r['status']);

        $this->girisYap();
        $this->assertSame(400, $this->get('yedekler.php?indir=' . urlencode('../products/x.jpg'))['status']);
    }

    public function testCronKlasoruWebdenCalistirilamaz(): void
    {
        $this->assertContains($this->get('cron/veritabani_yedekle.php')['status'], [403, 404]);
        $this->assertContains($this->get('cron/resimleri_r2ye_tasi.php')['status'], [403, 404]);
    }
}

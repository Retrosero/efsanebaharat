<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GuvenlikTest extends TestCase
{
    private array $geciciDosyalar = [];

    protected function setUp(): void
    {
        $_SESSION = ['csrf_token' => str_repeat('a', 64)];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->geciciDosyalar as $d) {
            @unlink($d);
        }
    }

    private function server(array $ek): array
    {
        return $ek + ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'panel.example.test'];
    }

    public function testGetIstekleriHerZamanGecer(): void
    {
        $this->assertTrue(csrfKaynakGecerliMi(['REQUEST_METHOD' => 'GET', 'HTTP_ORIGIN' => 'https://kotu.example']));
    }

    public function testAyniKaynakliPostGecer(): void
    {
        $this->assertTrue(csrfKaynakGecerliMi($this->server(['HTTP_ORIGIN' => 'https://panel.example.test'])));
        $this->assertTrue(csrfKaynakGecerliMi($this->server(['HTTP_REFERER' => 'https://panel.example.test/satis.php'])));
    }

    public function testPortluHostGecer(): void
    {
        $this->assertTrue(csrfKaynakGecerliMi([
            'REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'localhost:8080', 'HTTP_ORIGIN' => 'http://localhost:8080',
        ]));
    }

    public function testProxyArkasindakiHostGecer(): void
    {
        $this->assertTrue(csrfKaynakGecerliMi([
            'REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'app:80', 'HTTP_X_FORWARDED_HOST' => 'panel.example.test',
            'HTTP_ORIGIN' => 'https://panel.example.test',
        ]));
    }

    public function testFarkliSitedenPostReddedilir(): void
    {
        $this->assertFalse(csrfKaynakGecerliMi($this->server(['HTTP_ORIGIN' => 'https://kotu.example'])));
        $this->assertFalse(csrfKaynakGecerliMi($this->server(['HTTP_REFERER' => 'https://kotu.example/panel.example.test'])));
        // Alt alan adı taklidi
        $this->assertFalse(csrfKaynakGecerliMi($this->server(['HTTP_ORIGIN' => 'https://panel.example.test.kotu.example'])));
    }

    public function testBasliksizPostTokenIleDogrulanir(): void
    {
        $this->assertFalse(csrfKaynakGecerliMi($this->server([])));
        $this->assertTrue(csrfKaynakGecerliMi($this->server(['HTTP_X_CSRF_TOKEN' => str_repeat('a', 64)])));
        $_POST['csrf_token'] = str_repeat('a', 64);
        $this->assertTrue(csrfKaynakGecerliMi($this->server([])));
        $_POST['csrf_token'] = 'yanlis';
        $this->assertFalse(csrfKaynakGecerliMi($this->server([])));
    }

    public function testCsrfTokenGecerli(): void
    {
        $this->assertTrue(csrfTokenGecerli(str_repeat('a', 64)));
        $this->assertFalse(csrfTokenGecerli(''));
        $this->assertFalse(csrfTokenGecerli(['dizi']));
        $_SESSION = [];
        $this->assertFalse(csrfTokenGecerli(''));
    }

    public function testHtmlKacis(): void
    {
        $this->assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', e("<script>alert('x')</script>"));
        $this->assertSame('', e(null));
    }

    public function testJsonScriptScriptEtiketiniKiramaz(): void
    {
        $json = jsonScript(['ad' => '</script><script>alert(1)</script>']);
        $this->assertStringNotContainsString('</script>', $json);
        $this->assertSame(['ad' => '</script><script>alert(1)</script>'], json_decode($json, true));
    }

    private function geciciDosya(string $icerik): string
    {
        $yol = tempnam(sys_get_temp_dir(), 'tst');
        file_put_contents($yol, $icerik);
        $this->geciciDosyalar[] = $yol;
        return $yol;
    }

    private function pngIcerik(): string
    {
        // 1x1 şeffaf PNG
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    }

    public function testGecerliResimKabulEdilir(): void
    {
        $tmp = $this->geciciDosya($this->pngIcerik());
        $this->assertSame('png', resimDosyasiDogrula(['name' => 'urun.PNG', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]));
    }

    public function testPhpDosyasiReddedilir(): void
    {
        $tmp = $this->geciciDosya('<?php system($_GET["c"]); ?>');
        $this->assertFalse(resimDosyasiDogrula(['name' => 'shell.php', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]));
    }

    public function testResimUzantiliAmaPhpIcerikliDosyaReddedilir(): void
    {
        $tmp = $this->geciciDosya('<?php system($_GET["c"]); ?>');
        $this->assertFalse(resimDosyasiDogrula(['name' => 'shell.jpg', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]));
    }

    public function testCiftUzantiReddedilir(): void
    {
        $tmp = $this->geciciDosya($this->pngIcerik());
        $this->assertFalse(resimDosyasiDogrula(['name' => 'resim.png.php', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]));
    }

    public function testBuyukVeHataliYuklemeReddedilir(): void
    {
        $tmp = $this->geciciDosya($this->pngIcerik());
        $this->assertFalse(resimDosyasiDogrula(['name' => 'a.png', 'tmp_name' => $tmp, 'error' => 0, 'size' => 20 * 1024 * 1024]));
        $this->assertFalse(resimDosyasiDogrula(['name' => 'a.png', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_PARTIAL, 'size' => 10]));
        $this->assertFalse(resimDosyasiDogrula([]));
    }

    public function testGuvenliYuklemeSilDizinGecisiniEngeller(): void
    {
        $this->assertFalse(guvenliYuklemeSil('../includes/db.php'));
        $this->assertFalse(guvenliYuklemeSil('uploads/../config.php'));
        $this->assertFalse(guvenliYuklemeSil('/etc/passwd'));
        $this->assertFalse(guvenliYuklemeSil(''));
        $this->assertFileExists(__DIR__ . '/../../config.php');
    }

    public function testGuvenliYuklemeSilUploadsAltindakiDosyayiSiler(): void
    {
        $klasor = __DIR__ . '/../../uploads/test_tmp';
        @mkdir($klasor, 0777, true);
        $dosya = $klasor . '/silinecek.png';
        file_put_contents($dosya, 'x');
        $this->assertTrue(guvenliYuklemeSil('uploads/test_tmp/silinecek.png'));
        $this->assertFileDoesNotExist($dosya);
        @rmdir($klasor);
    }
}

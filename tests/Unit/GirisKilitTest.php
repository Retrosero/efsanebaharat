<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class GirisKilitTest extends TestCase
{
    private string $ip;

    protected function setUp(): void
    {
        $this->ip = 'test-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        girisDenemeleriniSifirla($this->ip);
    }

    public function testOnHataliDenemedenSonraKilitlenir(): void
    {
        for ($i = 0; $i < 9; $i++) {
            girisBasarisizKaydet($this->ip);
        }
        $this->assertFalse(girisKilitliMi($this->ip), '9 denemede henüz kilitlenmemeli');
        girisBasarisizKaydet($this->ip);
        $this->assertTrue(girisKilitliMi($this->ip), '10. denemeden sonra kilitlenmeli');
    }

    public function testBasariliGiristeSayacSifirlanir(): void
    {
        for ($i = 0; $i < 10; $i++) {
            girisBasarisizKaydet($this->ip);
        }
        girisDenemeleriniSifirla($this->ip);
        $this->assertFalse(girisKilitliMi($this->ip));
    }

    public function testSureDoluncaKilitKalkar(): void
    {
        for ($i = 0; $i < 10; $i++) {
            girisBasarisizKaydet($this->ip);
        }
        // Süreyi -1 saniye vererek pencerenin dolduğunu simüle et
        $this->assertFalse(girisKilitliMi($this->ip, 10, -1));
    }

    public function testFarkliIpEtkilenmez(): void
    {
        for ($i = 0; $i < 10; $i++) {
            girisBasarisizKaydet($this->ip);
        }
        $this->assertFalse(girisKilitliMi($this->ip . '-baska'));
    }
}

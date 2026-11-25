<?php
// includes/birim.php
// Ağırlık birimi (kg / gr) dönüşümleri ve satış kalemi hesapları (assets/js/birim.js ile aynı mantık).

const AGIRLIK_CARPANLARI = ['kg' => 1000, 'gr' => 1];

function agirlikBirimiMi($birim)
{
    return is_string($birim) && isset(AGIRLIK_CARPANLARI[$birim]);
}

/** Miktarı $kaynak birimden $hedef birime çevirir. Ağırlık dışı birimlerde aynen döner. */
function birimMiktarCevir($miktar, $kaynak, $hedef)
{
    $miktar = (float)$miktar;
    if ($kaynak === $hedef || !agirlikBirimiMi($kaynak) || !agirlikBirimiMi($hedef)) {
        return $miktar;
    }
    return $miktar * AGIRLIK_CARPANLARI[$kaynak] / AGIRLIK_CARPANLARI[$hedef];
}

/** $kaynak birimine ait birim fiyatı $hedef birimin birim fiyatına çevirir (500 TL/kg -> 0.5 TL/gr). */
function birimFiyatCevir($fiyat, $kaynak, $hedef)
{
    $fiyat = (float)$fiyat;
    if ($kaynak === $hedef || !agirlikBirimiMi($kaynak) || !agirlikBirimiMi($hedef)) {
        return $fiyat;
    }
    return $fiyat * AGIRLIK_CARPANLARI[$hedef] / AGIRLIK_CARPANLARI[$kaynak];
}

/**
 * Sepet kalemini doğrular ve ürünün kendi birimine normalize eder.
 *
 * @param array $item   ['id','qty','price','olcumBirimi']  (qty/price satış biriminde)
 * @param string $urunBirimi ürünün veritabanındaki ölçüm birimi
 * @return array ['miktar' => ürün biriminde, 'birim_fiyat' => ürün biriminde, 'toplam' => tutar]
 * @throws InvalidArgumentException
 */
function satisKalemiHesapla(array $item, $urunBirimi)
{
    $satisBirimi = $item['olcumBirimi'] ?? $urunBirimi;
    if (!is_string($satisBirimi) || !in_array($satisBirimi, ['adet', 'kg', 'gr'], true)) {
        throw new InvalidArgumentException('Geçersiz ölçü birimi');
    }
    // Ağırlık ürünü adetle, adet ürünü ağırlıkla satılamaz
    if (agirlikBirimiMi($satisBirimi) !== agirlikBirimiMi($urunBirimi)) {
        throw new InvalidArgumentException('Ölçü birimi ürünle uyumsuz');
    }

    $qty = $item['qty'] ?? 1;
    $price = $item['price'] ?? 0;
    if (!is_numeric($qty) || !is_numeric($price)) {
        throw new InvalidArgumentException('Geçersiz miktar veya fiyat');
    }
    $qty = (float)$qty;
    $price = (float)$price;
    if (!is_finite($qty) || $qty <= 0 || !is_finite($price) || $price < 0) {
        throw new InvalidArgumentException('Miktar sıfırdan büyük, fiyat negatif olmamalı');
    }

    return [
        'miktar' => birimMiktarCevir($qty, $satisBirimi, $urunBirimi),
        'birim_fiyat' => birimFiyatCevir($price, $satisBirimi, $urunBirimi),
        'toplam' => round($qty * $price, 4),
    ];
}

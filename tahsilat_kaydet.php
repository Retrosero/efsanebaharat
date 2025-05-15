<?php

// ... mevcut tahsilat kaydetme işlemi

// Tahsilat başarıyla kaydedildikten sonra müşteri bakiyesini güncelle
if ($tahsilatBasarili) {
    // Müşteri bakiyesini azalt (tahsilat tutarı kadar)
    musteriBakiyeGuncelle($pdo, $musteri_id, -$tahsilat_tutari);
} 
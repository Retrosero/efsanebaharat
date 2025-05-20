<?php
// satis_kaydet.php
header('Content-Type: application/json; charset=utf-8');
require_once 'includes/db.php';
// Oturum bilgilerini kontrol et
session_start();

// Kullanıcı giriş yapmamışsa
if (!isset($_SESSION['kullanici_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum açmanız gerekiyor']);
    exit;
}

// Hata ayıklama için
error_log("satis_kaydet.php çağrıldı");

// POST verilerini al
$raw_input = file_get_contents('php://input');
error_log("Ham POST verisi: " . $raw_input);

$postData = json_decode($raw_input, true);
if(!$postData){
   error_log("JSON decode hatası: " . json_last_error_msg());
   echo json_encode(['success'=>false,'message'=>'Geçersiz veri']);
   exit;
}

$musteri_id  = $postData['musteri_id'] ?? null;
$items       = $postData['items']      ?? [];
$discountRate= floatval($postData['discountRate']??0);
$note        = $postData['note']       ?? '';

error_log("Müşteri ID: " . $musteri_id);
error_log("Ürün sayısı: " . count($items));
error_log("İndirim oranı: " . $discountRate);

// Basit kontrol
if(!$musteri_id || empty($items)){
   error_log("Müşteri veya ürün listesi eksik");
   echo json_encode(['success'=>false,'message'=>'Müşteri veya ürün listesi yok']);
   exit;
}

try {
    // 1) Stok kontrolü
    foreach($items as $item) {
        $urun_id = intval($item['id']);
        $qty = floatval($item['qty'] ?? 1);
        $olcumBirimi = $item['olcumBirimi'] ?? 'adet';
        
        error_log("Stok kontrolü: Ürün ID: $urun_id, Miktar: $qty, Birim: $olcumBirimi");
        
        // Stok kontrolü
        $stmtStok = $pdo->prepare("SELECT stok_miktari, urun_adi, olcum_birimi FROM urunler WHERE id = ?");
        $stmtStok->execute([$urun_id]);
        $urun = $stmtStok->fetch(PDO::FETCH_ASSOC);
        
        if (!$urun) {
            error_log("Ürün bulunamadı: ID: $urun_id");
            echo json_encode([
                'success' => false,
                'message' => 'Ürün bulunamadı! Ürün ID: '.$urun_id
            ]);
            exit;
        }
        
        $stok = $urun['stok_miktari'];
        $urunOlcumBirimi = $urun['olcum_birimi'];
        
        // Birim dönüşümü yap
        if ($olcumBirimi != $urunOlcumBirimi) {
            if ($olcumBirimi == 'gr' && $urunOlcumBirimi == 'kg') {
                // gr -> kg dönüşümü (1000 gr = 1 kg)
                $qty = $qty / 1000;
            } else if ($olcumBirimi == 'kg' && $urunOlcumBirimi == 'gr') {
                // kg -> gr dönüşümü (1 kg = 1000 gr)
                $qty = $qty * 1000;
            }
        }
        
        if ($stok < $qty) {
            error_log("Yetersiz stok: Ürün: {$urun['urun_adi']}, Mevcut: $stok, İstenen: $qty");
            echo json_encode([
                'success' => false,
                'message' => 'Yetersiz stok! Ürün: '.$urun['urun_adi'].' (ID: '.$urun_id.'), Mevcut: '.$stok.' '.$urunOlcumBirimi.', İstenen: '.$qty.' '.$olcumBirimi
            ]);
            exit;
        }
    }

    // 2) Toplam tutar hesapla
    $araToplam = 0;
    foreach($items as $it){
        $q = floatval($it['qty'] ?? 1);
        $p = floatval($it['price'] ?? 0);
        $araToplam += ($q * $p);
    }
    $iskonto = $araToplam * ($discountRate/100);
    $netToplam = $araToplam - $iskonto;

    error_log("İskonto Hesaplama: Ara Toplam: {$araToplam}, İskonto Oranı: {$discountRate}%, İskonto Tutarı: {$iskonto}, Net Toplam: {$netToplam}");

    // Transaction başlat
    error_log("Transaction başlatılıyor");
    $pdo->beginTransaction();

    // 3) Faturalar tablosuna ekle - doğrudan onaylanmış olarak işaretliyoruz
    error_log("Fatura ekleniyor - doğrudan onaylanıyor");
    
    try {
    $stmtF = $pdo->prepare("
        INSERT INTO faturalar(
                fatura_turu, musteri_id, toplam_tutar, odeme_durumu,
                fatura_tarihi, kullanici_id, indirim_tutari, genel_toplam, kalan_tutar,
                created_at, onay_durumu, onayli
        ) VALUES(
                'satis', :mid, :toplam, 'odenmedi',
                CURDATE(), :kullanici_id, :iskonto, :net_toplam, :net_toplam,
                NOW(), 'onaylandi', 1
        )
    ");
        
    $stmtF->execute([
        ':mid' => $musteri_id,
        ':toplam' => $araToplam,
        ':iskonto' => $iskonto,
        ':net_toplam' => $netToplam,
        ':kullanici_id' => $_SESSION['kullanici_id']
    ]);
        
    $fatura_id = $pdo->lastInsertId();
        error_log("Fatura kaydedildi. Fatura ID: {$fatura_id}, Toplam: {$araToplam}, İskonto: {$iskonto}, Net Toplam: {$netToplam}");
        
        // 4) Fatura detaylarını ekle
        error_log("Fatura detayları ekleniyor");
        
        foreach($items as $item) {
            $urun_id = intval($item['id']);
            $qty = floatval($item['qty'] ?? 1);
            $price = floatval($item['price'] ?? 0);
            $olcumBirimi = $item['olcumBirimi'] ?? 'adet';
            $note = $item['note'] ?? '';
            
            // Ürün bilgilerini al
            $stmtU = $pdo->prepare("SELECT urun_adi, kdv_orani, olcum_birimi FROM urunler WHERE id = ?");
            $stmtU->execute([$urun_id]);
            $urun = $stmtU->fetch(PDO::FETCH_ASSOC);
            
            if (!$urun) {
                throw new Exception("Ürün bulunamadı: ID: $urun_id");
            }
            
            $urunOlcumBirimi = $urun['olcum_birimi'];
            
            // Birim dönüşümü yap
            if ($olcumBirimi != $urunOlcumBirimi) {
                if ($olcumBirimi == 'gr' && $urunOlcumBirimi == 'kg') {
                    // gr -> kg dönüşümü (1000 gr = 1 kg)
                    $qty = $qty / 1000;
                } else if ($olcumBirimi == 'kg' && $urunOlcumBirimi == 'gr') {
                    // kg -> gr dönüşümü (1 kg = 1000 gr)
                    $qty = $qty * 1000;
                }
            }
            
            $kdv_orani = floatval($urun['kdv_orani']);
            $toplam = $qty * $price;
            
            // Ürün bazında iskonto hesapla
            $urun_iskonto_orani = $discountRate;
            $urun_iskonto_tutari = $toplam * ($urun_iskonto_orani / 100);
            
            // KDV iskonto sonrası tutar üzerinden hesaplanır
            $iskonto_sonrasi_tutar = $toplam - $urun_iskonto_tutari;
            $kdv_tutari = $iskonto_sonrasi_tutar * ($kdv_orani / 100);
            $net_tutar = $iskonto_sonrasi_tutar + $kdv_tutari;

            error_log("Ürün İskonto Hesaplama (ID: {$urun_id}): Toplam: {$toplam}, İskonto Oranı: {$urun_iskonto_orani}%, İskonto Tutarı: {$urun_iskonto_tutari}, Net: {$net_tutar}");
            
            $stmtD = $pdo->prepare("
                INSERT INTO fatura_detaylari(
                    fatura_id, urun_id, urun_adi, miktar, olcum_birimi,
                    birim_fiyat, toplam_fiyat, kdv_orani, kdv_tutari,
                    indirim_orani, indirim_tutari, net_tutar, urun_notu
                ) VALUES(
                    :fid, :uid, :uadi, :qty, :olcum_birimi,
                    :price, :toplam, :kdv_oran, :kdv_tutar,
                    :indirim_orani, :indirim_tutari, :net, :note
                )
            ");
            
            $stmtD->execute([
                ':fid' => $fatura_id,
                ':uid' => $urun_id,
                ':uadi' => $urun['urun_adi'],
                ':qty' => $qty,
                ':olcum_birimi' => $urunOlcumBirimi,
                ':price' => $price,
                ':toplam' => $toplam,
                ':kdv_oran' => $kdv_orani,
                ':kdv_tutar' => $kdv_tutari,
                ':indirim_orani' => $urun_iskonto_orani,
                ':indirim_tutari' => $urun_iskonto_tutari,
                ':net' => $net_tutar,
                ':note' => $note
            ]);
            
            // 5) Stokları hemen güncelle
            error_log("Stok güncelleniyor: Ürün ID: {$urun_id}, Miktar: {$qty}");
        
            // Ürün stoklarını azalt
            $stmtStokGuncelle = $pdo->prepare("
                UPDATE urunler SET stok_miktari = stok_miktari - :qty WHERE id = :uid
            ");
            $stmtStokGuncelle->execute([':qty' => $qty, ':uid' => $urun_id]);
            
            // Stok hareketi ekle
            $stmtStokHareket = $pdo->prepare("
                INSERT INTO stok_hareketleri(
                    urun_id, hareket_tipi, miktar, fatura_id, 
                    kullanici_id, aciklama, created_at
            ) VALUES(
                    :uid, 'cikis', :qty, :fid,
                    :kullanici_id, :aciklama, NOW()
            )
        ");
            $stmtStokHareket->execute([
                ':uid' => $urun_id,
                ':qty' => $qty,
                ':fid' => $fatura_id,
                ':kullanici_id' => $_SESSION['kullanici_id'],
                ':aciklama' => 'Satış faturası stok çıkışı'
            ]);
        }
        
        // 6) Doğrudan cari hesaba yansıt
        error_log("Cari hesap güncelleniyor");
        
        // Müşteri cari bakiyesine borç olarak ekle
        $stmtMusteri = $pdo->prepare("
            UPDATE musteriler 
            SET cari_bakiye = cari_bakiye + :tutar 
            WHERE id = :musteri_id
        ");
        $stmtMusteri->execute([
            ':tutar' => $netToplam,
            ':musteri_id' => $musteri_id
        ]);
        
        // Transaction tamamlandı
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Satış başarıyla kaydedildi ve onay için gönderildi',
            'fatura_id' => $fatura_id,
            'redirect' => 'onay_merkezi.php'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Hata: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'İşlem sırasında hata oluştu: ' . $e->getMessage()
        ]);
    }
} catch (Exception $e) {
    error_log("Hata: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'İşlem sırasında hata oluştu: ' . $e->getMessage()
    ]);
}
?>

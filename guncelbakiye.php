<?php
require_once 'includes/db.php';

if (!function_exists('getMusteriCariBakiye')) {
    function getMusteriCariBakiye($pdo, $musteri_id, $para_birimi = null) {
        try {
            // Toplam satışları hesapla
            $sql = "
                SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM faturalar
                WHERE musteri_id = :mid AND fatura_turu = 'satis'
            ";
            
            // Para birimi filtresi ekle
            if ($para_birimi !== null) {
                $sql .= " AND para_birimi = :para_birimi";
            }
            
            $stmtSatis = $pdo->prepare($sql);
            if ($para_birimi !== null) {
                $stmtSatis->execute([':mid' => $musteri_id, ':para_birimi' => $para_birimi]);
            } else {
            $stmtSatis->execute([':mid' => $musteri_id]);
            }
            $toplamSatis = $stmtSatis->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Toplam alışları hesapla
            $sql = "
                SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM faturalar
                WHERE musteri_id = :mid AND fatura_turu = 'alis'
            ";
            
            // Para birimi filtresi ekle
            if ($para_birimi !== null) {
                $sql .= " AND para_birimi = :para_birimi";
            }
            
            $stmtAlis = $pdo->prepare($sql);
            if ($para_birimi !== null) {
                $stmtAlis->execute([':mid' => $musteri_id, ':para_birimi' => $para_birimi]);
            } else {
            $stmtAlis->execute([':mid' => $musteri_id]);
            }
            $toplamAlis = $stmtAlis->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Toplam tahsilatları hesapla
            $sql = "
                SELECT COALESCE(SUM(tutar), 0) as toplam
                FROM odeme_tahsilat
                WHERE musteri_id = :mid
            ";
            
            $stmtTahsilat = $pdo->prepare($sql);
            $stmtTahsilat->execute([':mid' => $musteri_id]);
            $toplamTahsilat = $stmtTahsilat->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Güncel bakiye = Satışlar - Alışlar - Tahsilatlar
            return $toplamSatis - $toplamAlis - $toplamTahsilat;
        } catch (PDOException $e) {
            error_log("Bakiye hesaplama hatası: " . $e->getMessage());
            return 0;
        }
    }
}

// Sadece TRY cinsinden bakiyeyi hesaplar
if (!function_exists('hesaplaGuncelBakiye')) {
    function hesaplaGuncelBakiye($pdo, $musteri_id) {
        // SATIŞLAR
            $stmtSatis = $pdo->prepare("
            SELECT COALESCE(SUM(toplam_tutar), 0) AS toplam
                FROM faturalar
            WHERE musteri_id = :musteri_id 
              AND fatura_turu = 'satis'
              AND para_birimi = 'TRY'
            ");
        $stmtSatis->execute([':musteri_id' => $musteri_id]);
        $satislar = $stmtSatis->fetchColumn();

        // ALIŞLAR
            $stmtAlis = $pdo->prepare("
            SELECT COALESCE(SUM(toplam_tutar), 0) AS toplam
                FROM faturalar
            WHERE musteri_id = :musteri_id 
              AND fatura_turu = 'alis'
              AND para_birimi = 'TRY'
            ");
        $stmtAlis->execute([':musteri_id' => $musteri_id]);
        $alislar = $stmtAlis->fetchColumn();

        // TAHSİLATLAR
            $stmtTahsilat = $pdo->prepare("
            SELECT COALESCE(SUM(tutar), 0) AS toplam
            FROM odeme_tahsilat 
            WHERE musteri_id = :musteri_id
              AND islem_turu = 'tahsilat'
        ");
        $stmtTahsilat->execute([':musteri_id' => $musteri_id]);
        $tahsilatlar = $stmtTahsilat->fetchColumn();
        
        // ÖDEMELER
        $stmtOdeme = $pdo->prepare("
            SELECT COALESCE(SUM(tutar), 0) AS toplam
                FROM odeme_tahsilat
            WHERE musteri_id = :musteri_id
              AND islem_turu = 'odeme'
            ");
        $stmtOdeme->execute([':musteri_id' => $musteri_id]);
        $odemeler = $stmtOdeme->fetchColumn();
        
        // Cari Bakiye Hesaplama:
        // Borçlar (Bakiyeyi artıran): Satışlar + Ödemeler
        // Alacaklar (Bakiyeyi azaltan): Alışlar + Tahsilatlar 
        $cariBakiye = $satislar + $odemeler - $alislar - $tahsilatlar;
        
        return $cariBakiye;
    }
}

// Tüm para birimleri için toplam bakiyeyi hesaplar
if (!function_exists('hesaplaTumParaBirimleriToplamBakiye')) {
    function hesaplaTumParaBirimleriToplamBakiye($pdo, $musteri_id) {
        // Her para birimi için bakiye hesapla
        $tryBakiye = getMusteriCariBakiye($pdo, $musteri_id, 'TRY');
        $usdBakiye = getMusteriCariBakiye($pdo, $musteri_id, 'USD');
        $eurBakiye = getMusteriCariBakiye($pdo, $musteri_id, 'EUR');
        $gbpBakiye = getMusteriCariBakiye($pdo, $musteri_id, 'GBP');

        // Para birimlerini birleştirmeden her birini ayrı döndür
        return [
            'TRY' => $tryBakiye,
            'USD' => $usdBakiye,
            'EUR' => $eurBakiye,
            'GBP' => $gbpBakiye
        ];
    }
}

// Döviz bakiyelerini güncellemek için yeni fonksiyonlar ekleyelim
function guncelleDovizbakiyeleri($pdo, $musteri_id) {
    // USD Bakiye
    $stmtUSD = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN fatura_turu = 'satis' THEN toplam_tutar ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN fatura_turu = 'alis' THEN toplam_tutar ELSE 0 END), 0) AS usd_bakiye
        FROM faturalar 
        WHERE musteri_id = :musteri_id 
          AND para_birimi = 'USD'
    ");
    $stmtUSD->execute([':musteri_id' => $musteri_id]);
    $usdBakiye = $stmtUSD->fetchColumn();
    
    // EUR Bakiye
    $stmtEUR = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN fatura_turu = 'satis' THEN toplam_tutar ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN fatura_turu = 'alis' THEN toplam_tutar ELSE 0 END), 0) AS eur_bakiye
        FROM faturalar 
        WHERE musteri_id = :musteri_id 
          AND para_birimi = 'EUR'
    ");
    $stmtEUR->execute([':musteri_id' => $musteri_id]);
    $eurBakiye = $stmtEUR->fetchColumn();
    
    // GBP Bakiye
    $stmtGBP = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN fatura_turu = 'satis' THEN toplam_tutar ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN fatura_turu = 'alis' THEN toplam_tutar ELSE 0 END), 0) AS gbp_bakiye
        FROM faturalar 
        WHERE musteri_id = :musteri_id 
          AND para_birimi = 'GBP'
    ");
    $stmtGBP->execute([':musteri_id' => $musteri_id]);
    $gbpBakiye = $stmtGBP->fetchColumn();
    
    // Veritabanında döviz bakiyelerini güncelle
    $stmtGuncelle = $pdo->prepare("
        UPDATE musteriler 
        SET usd_bakiye = :usd_bakiye,
            eur_bakiye = :eur_bakiye,
            gbp_bakiye = :gbp_bakiye 
        WHERE id = :musteri_id
    ");
    
    $stmtGuncelle->execute([
        ':usd_bakiye' => $usdBakiye,
        ':eur_bakiye' => $eurBakiye,
        ':gbp_bakiye' => $gbpBakiye,
        ':musteri_id' => $musteri_id
    ]);
    
    return [
        'usd' => $usdBakiye,
        'eur' => $eurBakiye,
        'gbp' => $gbpBakiye
    ];
}
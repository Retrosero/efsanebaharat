<?php
require_once 'includes/db.php';

if (!function_exists('getMusteriCariBakiye')) {
    function getMusteriCariBakiye($pdo, $musteri_id) {
        try {
            // Toplam satışları hesapla
            $stmtSatis = $pdo->prepare("
                SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM faturalar
                WHERE musteri_id = :mid AND fatura_turu = 'satis'
            ");
            $stmtSatis->execute([':mid' => $musteri_id]);
            $toplamSatis = $stmtSatis->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Toplam alışları hesapla
            $stmtAlis = $pdo->prepare("
                SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM faturalar
                WHERE musteri_id = :mid AND fatura_turu = 'alis'
            ");
            $stmtAlis->execute([':mid' => $musteri_id]);
            $toplamAlis = $stmtAlis->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Toplam tahsilatları hesapla
            $stmtTahsilat = $pdo->prepare("
                SELECT COALESCE(SUM(tutar), 0) as toplam
                FROM odeme_tahsilat
                WHERE musteri_id = :mid
            ");
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

if (!function_exists('hesaplaGuncelBakiye')) {
    function hesaplaGuncelBakiye($pdo, $musteri_id) {
        try {
            // Toplam satışları hesapla
            $stmtSatis = $pdo->prepare("
                SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM faturalar
                WHERE musteri_id = :mid AND fatura_turu = 'satis'
            ");
            $stmtSatis->execute([':mid' => $musteri_id]);
            $toplamSatis = $stmtSatis->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Toplam alışları hesapla
            $stmtAlis = $pdo->prepare("
                SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
                FROM faturalar
                WHERE musteri_id = :mid AND fatura_turu = 'alis'
            ");
            $stmtAlis->execute([':mid' => $musteri_id]);
            $toplamAlis = $stmtAlis->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Toplam tahsilatları hesapla
            $stmtTahsilat = $pdo->prepare("
                SELECT COALESCE(SUM(tutar), 0) as toplam
                FROM odeme_tahsilat
                WHERE musteri_id = :mid
            ");
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
<?php
require_once 'includes/db.php';

if (!function_exists('getMusteriCariBakiye')) {
    function getMusteriCariBakiye($pdo, $musteri_id) {
        // Cari bakiye hesaplama işlemleri
        $stmt = $pdo->prepare("SELECT SUM(t.tutar) AS toplam_tahsilat FROM odeme_tahsilat t WHERE t.musteri_id = :musteri_id");
        $stmt->execute([':musteri_id' => $musteri_id]);
        $toplam_tahsilat = $stmt->fetchColumn();

        // Müşteri bakiyesini döndür
        return $toplam_tahsilat ?: 0;
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

            // Toplam tahsilatları hesapla
            $stmtTahsilat = $pdo->prepare("
                SELECT COALESCE(SUM(tutar), 0) as toplam
                FROM odeme_tahsilat
                WHERE musteri_id = :mid
            ");
            $stmtTahsilat->execute([':mid' => $musteri_id]);
            $toplamTahsilat = $stmtTahsilat->fetch(PDO::FETCH_ASSOC)['toplam'];

            // Güncel bakiye = Satışlar - Tahsilatlar
            return $toplamSatis - $toplamTahsilat;
        } catch (PDOException $e) {
            error_log("Bakiye hesaplama hatası: " . $e->getMessage());
            return 0;
        }
    }
}
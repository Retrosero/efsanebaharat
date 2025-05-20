<?php
require_once("../includes/session.php");
require_once("../includes/db.php");
require_once("../includes/functions.php");

// Yetki kontrolü
if (!$_SESSION['user_id'] || !yetkiKontrol('gunsonu', 'goruntuleme')) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit();
}

// POST verisi kontrolü
if (!isset($_POST['tarih'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Tarih parametresi gerekli']);
    exit();
}

$tarih = $_POST['tarih'];

try {
    // Satışlar
    $stmtSatis = $pdo->prepare("
        SELECT f.*, m.ad as musteri_ad, m.soyad as musteri_soyad 
        FROM faturalar f
        LEFT JOIN musteriler m ON f.musteri_id = m.id
        WHERE f.fatura_turu = 'satis' 
        AND DATE(f.created_at) = ?
        AND f.iptal = 0
    ");
    $stmtSatis->execute([$tarih]);
    $satislar = [];
    while ($row = $stmtSatis->fetch(PDO::FETCH_ASSOC)) {
        $satislar[] = [
            $row['fatura_no'],
            $row['musteri_ad'] . ' ' . $row['musteri_soyad'],
            number_format($row['toplam_tutar'], 2, ',', '.'),
            number_format($row['vergi_tutari'], 2, ',', '.'),
            number_format($row['genel_toplam'], 2, ',', '.'),
            ucfirst($row['odeme_durumu']),
            '<a href="fatura_detay.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>'
        ];
    }

    // Alışlar
    $stmtAlis = $pdo->prepare("
        SELECT f.*, t.firma_adi as tedarikci_ad 
        FROM faturalar f
        LEFT JOIN tedarikciler t ON f.tedarikci_id = t.id
        WHERE f.fatura_turu = 'alis' 
        AND DATE(f.created_at) = ?
        AND f.iptal = 0
    ");
    $stmtAlis->execute([$tarih]);
    $alislar = [];
    while ($row = $stmtAlis->fetch(PDO::FETCH_ASSOC)) {
        $alislar[] = [
            $row['fatura_no'],
            $row['tedarikci_ad'],
            number_format($row['toplam_tutar'], 2, ',', '.'),
            number_format($row['vergi_tutari'], 2, ',', '.'),
            number_format($row['genel_toplam'], 2, ',', '.'),
            ucfirst($row['odeme_durumu']),
            '<a href="fatura_detay.php?id=' . $row['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>'
        ];
    }

    // Tahsilat ve Ödemeler
    $stmtTahsilatOdeme = $pdo->prepare("
        SELECT ot.*, 
               m.ad as musteri_ad, m.soyad as musteri_soyad,
               t.firma_adi as tedarikci_ad
        FROM odeme_tahsilat ot
        LEFT JOIN musteriler m ON ot.musteri_id = m.id
        LEFT JOIN tedarikciler t ON ot.tedarikci_id = t.id
        WHERE DATE(ot.islem_tarihi) = ?
    ");
    $stmtTahsilatOdeme->execute([$tarih]);
    $tahsilat_odeme = [];
    while ($row = $stmtTahsilatOdeme->fetch(PDO::FETCH_ASSOC)) {
        $islemTuru = $row['islem_turu'] == 'tahsilat' ? 'Tahsilat' : 'Ödeme';
        $cariAd = $row['islem_turu'] == 'tahsilat' ? 
                  ($row['musteri_ad'] . ' ' . $row['musteri_soyad']) : 
                  $row['tedarikci_ad'];
        
        $tahsilat_odeme[] = [
            $islemTuru,
            $cariAd,
            number_format($row['tutar'], 2, ',', '.'),
            ucfirst($row['odeme_turu']),
            $row['aciklama'],
            '<a href="javascript:void(0)" onclick="islemDetay(' . $row['id'] . ')" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a>'
        ];
    }

    // Özet verileri
    $stmtOzet = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN fatura_turu = 'satis' AND iptal = 0 THEN genel_toplam ELSE 0 END), 0) as toplam_satis,
            COALESCE(SUM(CASE WHEN fatura_turu = 'alis' AND iptal = 0 THEN genel_toplam ELSE 0 END), 0) as toplam_alis
        FROM faturalar 
        WHERE DATE(created_at) = ?
    ");
    $stmtOzet->execute([$tarih]);
    $ozet = $stmtOzet->fetch(PDO::FETCH_ASSOC);

    $stmtTahsilatOzetler = $pdo->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN islem_turu = 'tahsilat' THEN tutar ELSE 0 END), 0) as toplam_tahsilat,
            COALESCE(SUM(CASE WHEN islem_turu = 'odeme' THEN tutar ELSE 0 END), 0) as toplam_odeme
        FROM odeme_tahsilat 
        WHERE DATE(islem_tarihi) = ?
    ");
    $stmtTahsilatOzetler->execute([$tarih]);
    $tahsilatOzetler = $stmtTahsilatOzetler->fetch(PDO::FETCH_ASSOC);

    // Sonuç
    $response = [
        'success' => true,
        'ozet' => [
            'satis' => number_format($ozet['toplam_satis'], 2, ',', '.'),
            'alis' => number_format($ozet['toplam_alis'], 2, ',', '.'),
            'tahsilat' => number_format($tahsilatOzetler['toplam_tahsilat'], 2, ',', '.'),
            'odeme' => number_format($tahsilatOzetler['toplam_odeme'], 2, ',', '.')
        ],
        'satislar' => $satislar,
        'alislar' => $alislar,
        'tahsilat_odeme' => $tahsilat_odeme
    ];

    header('Content-Type: application/json');
    echo json_encode($response);

} catch(PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Veritabanı hatası: ' . $e->getMessage()
    ]);
} 
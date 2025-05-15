<?php
require_once 'includes/db.php';

header('Content-Type: application/json');

if (!isset($_POST['islem'])) {
    echo json_encode(['success' => false, 'message' => 'İşlem belirtilmedi']);
    exit;
}

$islem = $_POST['islem'];
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

try {
    switch ($islem) {
        case 'tahsilat_sil':
            if ($id > 0) {
                // Önce tahsilatın tutarını al
                $stmt = $pdo->prepare("SELECT tutar, musteri_id FROM odeme_tahsilat WHERE id = ?");
                $stmt->execute([$id]);
                $tahsilat = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($tahsilat) {
                    $pdo->beginTransaction();
                    
                    // Tahsilatı sil
                    $stmt = $pdo->prepare("DELETE FROM odeme_tahsilat WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Müşteri bakiyesini güncelle (tahsilat silindiği için bakiyeye ekle)
                    $stmt = $pdo->prepare("UPDATE musteriler SET cari_bakiye = cari_bakiye + ? WHERE id = ?");
                    $stmt->execute([$tahsilat['tutar'], $tahsilat['musteri_id']]);
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Tahsilat başarıyla silindi']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Tahsilat bulunamadı']);
                }
            }
            break;
            
        case 'fatura_sil':
            if ($id > 0) {
                // Önce faturanın bilgilerini al (tür, tutar, müşteri/tedarikçi ID)
                $stmt = $pdo->prepare("SELECT fatura_turu, toplam_tutar, musteri_id, tedarikci_id FROM faturalar WHERE id = ?");
                $stmt->execute([$id]);
                $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($fatura) {
                    $pdo->beginTransaction();
                    
                    // Fatura detaylarını sil
                    $stmt = $pdo->prepare("DELETE FROM fatura_detaylari WHERE fatura_id = ?");
                    $stmt->execute([$id]);
                    
                    // Faturayı sil
                    $stmt = $pdo->prepare("DELETE FROM faturalar WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Fatura türüne göre müşteri veya tedarikçi bakiyesini güncelle
                    if ($fatura['fatura_turu'] == 'satis' && $fatura['musteri_id']) {
                        // Satış faturası silindiğinde müşteri bakiyesinden düş
                        $stmt = $pdo->prepare("UPDATE musteriler SET cari_bakiye = cari_bakiye - ? WHERE id = ?");
                        $stmt->execute([$fatura['toplam_tutar'], $fatura['musteri_id']]);
                    } elseif ($fatura['fatura_turu'] == 'alis' && $fatura['tedarikci_id']) {
                        // Alış faturası silindiğinde tedarikçi bakiyesine ekle
                        $stmt = $pdo->prepare("UPDATE musteriler SET cari_bakiye = cari_bakiye + ? WHERE id = ?");
                        $stmt->execute([$fatura['toplam_tutar'], $fatura['tedarikci_id']]);
                    }
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Fatura başarıyla silindi']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Fatura bulunamadı']);
                }
            }
            break;
            
        case 'fatura_detay_getir':
            if ($id > 0) {
                // Fatura bilgilerini al
                $stmt = $pdo->prepare("
                    SELECT f.*, 
                           CASE 
                             WHEN f.fatura_turu = 'satis' THEN m.ad 
                             WHEN f.fatura_turu = 'alis' THEN t.ad 
                           END AS firma_ad,
                           CASE 
                             WHEN f.fatura_turu = 'satis' THEN m.soyad 
                             WHEN f.fatura_turu = 'alis' THEN t.soyad 
                           END AS firma_soyad
                    FROM faturalar f
                    LEFT JOIN musteriler m ON f.musteri_id = m.id AND f.fatura_turu = 'satis'
                    LEFT JOIN musteriler t ON f.tedarikci_id = t.id AND f.fatura_turu = 'alis'
                    WHERE f.id = ?
                ");
                $stmt->execute([$id]);
                $fatura = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($fatura) {
                    // Fatura detaylarını al
                    $stmtD = $pdo->prepare("
                        SELECT fd.*, u.urun_adi, u.urun_kodu
                        FROM fatura_detaylari fd
                        JOIN urunler u ON fd.urun_id = u.id
                        WHERE fd.fatura_id = ?
                    ");
                    $stmtD->execute([$id]);
                    $detaylar = $stmtD->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Detayları düzenle
                    $items = [];
                    foreach ($detaylar as $row) {
                        $items[] = [
                            'id' => $row['id'],
                            'urun_id' => $row['urun_id'],
                            'urun_adi' => $row['urun_adi'],
                            'urun_kodu' => $row['urun_kodu'] ?? 'PRD' . $row['urun_id'],
                            'miktar' => (int)$row['miktar'],
                            'birim_fiyat' => (float)$row['birim_fiyat'],
                            'aciklama' => $row['aciklama']
                        ];
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'fatura' => $fatura,
                        'detaylar' => $items
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Fatura bulunamadı']);
                }
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Geçersiz işlem']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
}
?> 
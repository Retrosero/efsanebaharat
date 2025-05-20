<?php
// get_islem_detay.php
require_once 'includes/db.php';
include 'includes/auth_check.php'; // Sadece oturum kontrolü içeren bir dosya

// ID kontrolü
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<p class="text-center text-red-500">Geçersiz işlem ID\'si.</p>';
    exit;
}

$islem_id = intval($_GET['id']);

try {
    // İşlem bilgilerini çek
    $sql = "
        SELECT oi.*, 
               k_ekleyen.kullanici_adi AS ekleyen_kullanici,
               k_onaylayan.kullanici_adi AS onaylayan_kullanici,
               m.ad AS musteri_ad, 
               m.soyad AS musteri_soyad,
               t.firma_adi AS tedarikci_adi,
               CASE 
                   WHEN oi.islem_tipi = 'satis' THEN f_satis.fatura_no
                   WHEN oi.islem_tipi = 'alis' THEN f_alis.fatura_no
                   WHEN oi.islem_tipi = 'tahsilat' THEN ot.evrak_no
                   WHEN oi.islem_tipi = 'odeme' THEN ot.evrak_no
                   ELSE oi.referans_no
               END AS belge_no,
               CASE 
                   WHEN oi.islem_tipi = 'satis' OR oi.islem_tipi = 'alis' THEN f.fatura_tarihi
                   WHEN oi.islem_tipi = 'tahsilat' OR oi.islem_tipi = 'odeme' THEN ot.islem_tarihi
                   ELSE NULL
               END AS islem_tarihi
        FROM onay_islemleri oi
        LEFT JOIN kullanicilar k_ekleyen ON oi.ekleyen_id = k_ekleyen.id
        LEFT JOIN kullanicilar k_onaylayan ON oi.onaylayan_id = k_onaylayan.id
        LEFT JOIN musteriler m ON oi.musteri_id = m.id
        LEFT JOIN tedarikciler t ON oi.tedarikci_id = t.id
        LEFT JOIN faturalar f_satis ON oi.islem_id = f_satis.id AND oi.islem_tipi = 'satis'
        LEFT JOIN faturalar f_alis ON oi.islem_id = f_alis.id AND oi.islem_tipi = 'alis'
        LEFT JOIN faturalar f ON (oi.islem_id = f.id AND (oi.islem_tipi = 'satis' OR oi.islem_tipi = 'alis'))
        LEFT JOIN odeme_tahsilat ot ON oi.islem_id = ot.id AND (oi.islem_tipi = 'tahsilat' OR oi.islem_tipi = 'odeme')
        WHERE oi.id = :id
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $islem_id]);
    $islem = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$islem) {
        echo '<p class="text-center text-red-500">İşlem bulunamadı.</p>';
        exit;
    }
    
    // İşlem detayları - İşlem tipine göre farklı tablolardan çek
    $detaylar = [];
    
    if ($islem['islem_tipi'] === 'satis' || $islem['islem_tipi'] === 'alis') {
        // Fatura detaylarını çek
        $sql_detay = "
            SELECT fd.*, u.urun_adi
            FROM fatura_detaylari fd
            LEFT JOIN urunler u ON fd.urun_id = u.id
            WHERE fd.fatura_id = :fatura_id
            ORDER BY fd.id
        ";
        
        $stmt_detay = $pdo->prepare($sql_detay);
        $stmt_detay->execute([':fatura_id' => $islem['islem_id']]);
        $detaylar = $stmt_detay->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // İşlem tipi çevirisi
    $islem_tipi_text = '';
    if ($islem['islem_tipi'] === 'satis') {
        $islem_tipi_text = 'Satış';
    } elseif ($islem['islem_tipi'] === 'alis') {
        $islem_tipi_text = 'Alış';
    } elseif ($islem['islem_tipi'] === 'tahsilat') {
        $islem_tipi_text = 'Tahsilat';
    } elseif ($islem['islem_tipi'] === 'odeme') {
        $islem_tipi_text = 'Ödeme';
    }
    
    // Durum sınıfları
    $durum_class = '';
    $durum_text = '';
    
    if ($islem['durum'] === 'bekliyor') {
        $durum_class = 'bg-yellow-100 text-yellow-800';
        $durum_text = 'Bekliyor';
    } elseif ($islem['durum'] === 'onaylandi') {
        $durum_class = 'bg-green-100 text-green-800';
        $durum_text = 'Onaylandı';
    } elseif ($islem['durum'] === 'reddedildi') {
        $durum_class = 'bg-red-100 text-red-800';
        $durum_text = 'Reddedildi';
    }
    
    // Firma bilgisi
    $firma_bilgisi = '';
    if (!empty($islem['musteri_ad'])) {
        $firma_bilgisi = $islem['musteri_ad'] . ' ' . $islem['musteri_soyad'];
    } elseif (!empty($islem['tedarikci_adi'])) {
        $firma_bilgisi = $islem['tedarikci_adi'];
    }
    
    // HTML çıktısı
    ?>
    <div class="space-y-6">
        <div class="grid grid-cols-2 gap-4">
            <div>
                <h3 class="text-lg font-medium mb-4">İşlem Bilgileri</h3>
                <div class="space-y-2">
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">İşlem Tipi:</span>
                        <span class="font-medium"><?= $islem_tipi_text ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Belge No:</span>
                        <span class="font-medium"><?= htmlspecialchars($islem['belge_no'] ?? $islem['referans_no'] ?? '-') ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600"><?= ($islem['islem_tipi'] === 'satis' || $islem['islem_tipi'] === 'tahsilat') ? 'Müşteri:' : 'Tedarikçi:' ?></span>
                        <span class="font-medium"><?= htmlspecialchars($firma_bilgisi) ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Tutar:</span>
                        <span class="font-medium"><?= number_format($islem['tutar'], 2, ',', '.') ?> ₺</span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">İşlem Tarihi:</span>
                        <span class="font-medium"><?= $islem['islem_tarihi'] ? date('d.m.Y', strtotime($islem['islem_tarihi'])) : date('d.m.Y', strtotime($islem['created_at'])) ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Ekleyen:</span>
                        <span class="font-medium"><?= htmlspecialchars($islem['ekleyen_kullanici'] ?? '-') ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Ekleme Tarihi:</span>
                        <span class="font-medium"><?= date('d.m.Y H:i', strtotime($islem['created_at'])) ?></span>
                    </div>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-medium mb-4">Onay Bilgileri</h3>
                <div class="space-y-2">
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Durum:</span>
                        <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?= $durum_class ?>">
                            <?= $durum_text ?>
                        </span>
                    </div>
                    <?php if ($islem['durum'] !== 'bekliyor'): ?>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">Onaylayan/Reddeden:</span>
                        <span class="font-medium"><?= htmlspecialchars($islem['onaylayan_kullanici'] ?? '-') ?></span>
                    </div>
                    <div class="flex justify-between border-b pb-2">
                        <span class="text-gray-600">İşlem Tarihi:</span>
                        <span class="font-medium"><?= $islem['onay_tarihi'] ? date('d.m.Y H:i', strtotime($islem['onay_tarihi'])) : '-' ?></span>
                    </div>
                    <div class="border-b pb-2">
                        <div class="text-gray-600 mb-1">Not:</div>
                        <div class="font-medium"><?= nl2br(htmlspecialchars($islem['onay_notu'] ?? '-')) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div>
            <h3 class="text-lg font-medium mb-4">Açıklama</h3>
            <div class="bg-gray-50 p-4 rounded">
                <?= nl2br(htmlspecialchars($islem['aciklama'] ?? 'Açıklama bulunmuyor.')) ?>
            </div>
        </div>
        
        <?php if (!empty($detaylar)): ?>
        <div>
            <h3 class="text-lg font-medium mb-4">İşlem Detayları</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">KDV</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($detaylar as $detay): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= htmlspecialchars($detay['urun_adi'] ?? $detay['urun_adi']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= number_format($detay['miktar'], 3, ',', '.') ?> <?= $detay['olcum_birimi'] ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= number_format($detay['birim_fiyat'], 2, ',', '.') ?> ₺
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                %<?= number_format($detay['kdv_orani'], 2, ',', '.') ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?= number_format($detay['net_tutar'], 2, ',', '.') ?> ₺
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
} catch (PDOException $e) {
    echo '<p class="text-center text-red-500">Hata: ' . $e->getMessage() . '</p>';
}
?> 
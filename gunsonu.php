<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Tarih seçimi için varsayılan değer bugün
$selectedDate = isset($_GET['tarih']) ? $_GET['tarih'] : date('Y-m-d');

try {
    // Para birimleri
    $paraBirimleri = ['TRY', 'USD', 'EUR', 'GBP'];
    
    // Her para birimi için toplamları tutacak diziler
    $satisToplam = ['TRY' => 0, 'USD' => 0, 'EUR' => 0, 'GBP' => 0];
    $alisToplam = ['TRY' => 0, 'USD' => 0, 'EUR' => 0, 'GBP' => 0];
    $tahsilatToplam = ['TRY' => 0, 'USD' => 0, 'EUR' => 0, 'GBP' => 0];
    $odemeToplam = ['TRY' => 0, 'USD' => 0, 'EUR' => 0, 'GBP' => 0];
    
    // Satış Faturaları
    $stmtSatis = $pdo->prepare("
        SELECT f.*, m.ad as musteri_ad, m.soyad as musteri_soyad,
               k.kullanici_adi as kaydeden, f.para_birimi
        FROM faturalar f
        LEFT JOIN musteriler m ON f.musteri_id = m.id
        LEFT JOIN kullanicilar k ON f.kullanici_id = k.id
        WHERE f.fatura_turu = 'satis' 
        AND DATE(f.created_at) = ?
        ORDER BY f.created_at DESC
    ");
    $stmtSatis->execute([$selectedDate]);
    $satislar = $stmtSatis->fetchAll(PDO::FETCH_ASSOC);

    // Alış Faturaları
    $stmtAlis = $pdo->prepare("
        SELECT f.*, t.firma_adi as tedarikci_ad,
               k.kullanici_adi as kaydeden, f.para_birimi
        FROM faturalar f
        LEFT JOIN tedarikciler t ON f.tedarikci_id = t.id
        LEFT JOIN kullanicilar k ON f.kullanici_id = k.id
        WHERE f.fatura_turu = 'alis' 
        AND DATE(f.created_at) = ?
        ORDER BY f.created_at DESC
    ");
    $stmtAlis->execute([$selectedDate]);
    $alislar = $stmtAlis->fetchAll(PDO::FETCH_ASSOC);

    // Tahsilatlar
    $stmtTahsilat = $pdo->prepare("
        SELECT ot.*, m.ad as musteri_ad, m.soyad as musteri_soyad,
               k.kullanici_adi as kaydeden, 'TRY' as para_birimi
        FROM odeme_tahsilat ot
        LEFT JOIN musteriler m ON ot.musteri_id = m.id
        LEFT JOIN kullanicilar k ON ot.kullanici_id = k.id
        WHERE ot.islem_turu = 'tahsilat'
        AND DATE(ot.created_at) = ?
        ORDER BY ot.created_at DESC
    ");
    $stmtTahsilat->execute([$selectedDate]);
    $tahsilatlar = $stmtTahsilat->fetchAll(PDO::FETCH_ASSOC);

    // Ödemeler (Tediye)
    $stmtOdeme = $pdo->prepare("
        SELECT ot.*, t.firma_adi as tedarikci_ad,
               k.kullanici_adi as kaydeden, 'TRY' as para_birimi
        FROM odeme_tahsilat ot
        LEFT JOIN tedarikciler t ON ot.tedarikci_id = t.id
        LEFT JOIN kullanicilar k ON ot.kullanici_id = k.id
        WHERE ot.islem_turu = 'odeme'
        AND DATE(ot.created_at) = ?
        ORDER BY ot.created_at DESC
    ");
    $stmtOdeme->execute([$selectedDate]);
    $odemeler = $stmtOdeme->fetchAll(PDO::FETCH_ASSOC);

    // Para birimine göre toplamları hesapla
    foreach ($satislar as $satis) {
        $paraBirimi = $satis['para_birimi'] ?? 'TRY';
        if (isset($satisToplam[$paraBirimi])) {
            $satisToplam[$paraBirimi] += $satis['genel_toplam'];
        }
    }
    
    foreach ($alislar as $alis) {
        $paraBirimi = $alis['para_birimi'] ?? 'TRY';
        if (isset($alisToplam[$paraBirimi])) {
            $alisToplam[$paraBirimi] += $alis['genel_toplam'];
        }
    }
    
    foreach ($tahsilatlar as $tahsilat) {
        $paraBirimi = $tahsilat['para_birimi'] ?? 'TRY'; 
        if (isset($tahsilatToplam[$paraBirimi])) {
            $tahsilatToplam[$paraBirimi] += $tahsilat['tutar'];
        }
    }
    
    foreach ($odemeler as $odeme) {
        $paraBirimi = $odeme['para_birimi'] ?? 'TRY';
        if (isset($odemeToplam[$paraBirimi])) {
            $odemeToplam[$paraBirimi] += $odeme['tutar'];
        }
    }

} catch(PDOException $e) {
    echo "Hata: " . $e->getMessage();
    exit;
}

// Para birimi sembollerini tanımla
$paraBirimiSembolleri = [
    'TRY' => '₺',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£'
];
?>

<div class="p-4 sm:p-6">
    <!-- Başlık ve Tarih Seçici -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4 sm:mb-0">Gün Sonu Raporu</h1>
        <form method="GET" class="flex items-center space-x-2">
            <input 
                type="date" 
                name="tarih" 
                value="<?= htmlspecialchars($selectedDate) ?>"
                class="border border-gray-300 rounded-lg px-3 py-2"
            >
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                Göster
            </button>
        </form>
    </div>

    <!-- Özet Kartları -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
        <!-- Satışlar Kartı -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Satış</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($satisToplam['TRY'], 2, ',', '.') ?> ₺</p>
                    <?php foreach(['USD', 'EUR', 'GBP'] as $para_birimi): ?>
                        <?php if($satisToplam[$para_birimi] > 0): ?>
                            <p class="text-sm text-gray-600"><?= number_format($satisToplam[$para_birimi], 2, ',', '.') ?> <?= $paraBirimiSembolleri[$para_birimi] ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="text-blue-500">
                    <i class="ri-shopping-cart-line text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Alışlar Kartı -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Alış</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($alisToplam['TRY'], 2, ',', '.') ?> ₺</p>
                    <?php foreach(['USD', 'EUR', 'GBP'] as $para_birimi): ?>
                        <?php if($alisToplam[$para_birimi] > 0): ?>
                            <p class="text-sm text-gray-600"><?= number_format($alisToplam[$para_birimi], 2, ',', '.') ?> <?= $paraBirimiSembolleri[$para_birimi] ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="text-green-500">
                    <i class="ri-store-line text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Tahsilatlar Kartı -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Tahsilat</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($tahsilatToplam['TRY'], 2, ',', '.') ?> ₺</p>
                    <?php foreach(['USD', 'EUR', 'GBP'] as $para_birimi): ?>
                        <?php if($tahsilatToplam[$para_birimi] > 0): ?>
                            <p class="text-sm text-gray-600"><?= number_format($tahsilatToplam[$para_birimi], 2, ',', '.') ?> <?= $paraBirimiSembolleri[$para_birimi] ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="text-purple-500">
                    <i class="ri-money-dollar-circle-line text-2xl"></i>
                </div>
            </div>
        </div>

        <!-- Ödemeler Kartı -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Ödeme</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($odemeToplam['TRY'], 2, ',', '.') ?> ₺</p>
                    <?php foreach(['USD', 'EUR', 'GBP'] as $para_birimi): ?>
                        <?php if($odemeToplam[$para_birimi] > 0): ?>
                            <p class="text-sm text-gray-600"><?= number_format($odemeToplam[$para_birimi], 2, ',', '.') ?> <?= $paraBirimiSembolleri[$para_birimi] ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="text-orange-500">
                    <i class="ri-bank-card-line text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    
    <!-- Detaylı Raporlar -->
    <div class="space-y-6">
        <!-- Satışlar -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Satışlar</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Saat</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Müşteri</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Para Birimi</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kaydeden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($satislar)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-2 text-center text-gray-500">Kayıt bulunamadı</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($satislar as $satis): ?>
                            <?php $paraBirimi = $satis['para_birimi'] ?? 'TRY'; ?>
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="openInvoiceModal(<?= $satis['id'] ?>)">
                                <td class="px-4 py-2"><?= date('H:i', strtotime($satis['created_at'])) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($satis['musteri_ad'] . ' ' . $satis['musteri_soyad']) ?></td>
                                <td class="px-4 py-2"><?= number_format($satis['genel_toplam'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2"><?= $paraBirimiSembolleri[$paraBirimi] ?> (<?= $paraBirimi ?>)</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch($satis['onay_durumu']) {
                                            case 'bekliyor':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'onaylandi':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'reddedildi':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($satis['onay_durumu']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($satis['kaydeden']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Alışlar -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Alışlar</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Saat</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tedarikçi</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Para Birimi</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kaydeden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($alislar)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-2 text-center text-gray-500">Kayıt bulunamadı</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($alislar as $alis): ?>
                            <?php $paraBirimi = $alis['para_birimi'] ?? 'TRY'; ?>
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="openInvoiceModal(<?= $alis['id'] ?>)">
                                <td class="px-4 py-2"><?= date('H:i', strtotime($alis['created_at'])) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($alis['tedarikci_ad']) ?></td>
                                <td class="px-4 py-2"><?= number_format($alis['genel_toplam'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2"><?= $paraBirimiSembolleri[$paraBirimi] ?> (<?= $paraBirimi ?>)</td>
                                <td class="px-4 py-2">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch($alis['onay_durumu']) {
                                            case 'bekliyor':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'onaylandi':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'reddedildi':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($alis['onay_durumu']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($alis['kaydeden']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tahsilatlar -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Tahsilatlar</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Saat</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Müşteri</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Para Birimi</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ödeme Türü</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kaydeden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($tahsilatlar)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-2 text-center text-gray-500">Kayıt bulunamadı</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($tahsilatlar as $tahsilat): ?>
                            <?php $paraBirimi = $tahsilat['para_birimi'] ?? 'TRY'; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?= date('H:i', strtotime($tahsilat['created_at'])) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($tahsilat['musteri_ad'] . ' ' . $tahsilat['musteri_soyad']) ?></td>
                                <td class="px-4 py-2"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2"><?= $paraBirimiSembolleri[$paraBirimi] ?> (<?= $paraBirimi ?>)</td>
                                <td class="px-4 py-2"><?= ucfirst($tahsilat['odeme_turu']) ?></td>
                                <td class="px-4 py-2">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch($tahsilat['onay_durumu']) {
                                            case 'bekliyor':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'onaylandi':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'reddedildi':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($tahsilat['onay_durumu']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($tahsilat['kaydeden']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Ödemeler -->
        <div class="bg-white rounded-lg shadow-sm">
            <div class="p-4 border-b">
                <h3 class="text-lg font-semibold">Ödemeler</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Saat</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tedarikçi</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tutar</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Para Birimi</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ödeme Türü</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Durum</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Kaydeden</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($odemeler)): ?>
                        <tr>
                            <td colspan="7" class="px-4 py-2 text-center text-gray-500">Kayıt bulunamadı</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($odemeler as $odeme): ?>
                            <?php $paraBirimi = $odeme['para_birimi'] ?? 'TRY'; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2"><?= date('H:i', strtotime($odeme['created_at'])) ?></td>
                                <td class="px-4 py-2"><?= htmlspecialchars($odeme['tedarikci_ad']) ?></td>
                                <td class="px-4 py-2"><?= number_format($odeme['tutar'], 2, ',', '.') ?></td>
                                <td class="px-4 py-2"><?= $paraBirimiSembolleri[$paraBirimi] ?> (<?= $paraBirimi ?>)</td>
                                <td class="px-4 py-2"><?= ucfirst($odeme['odeme_turu']) ?></td>
                                <td class="px-4 py-2">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php
                                        switch($odeme['onay_durumu']) {
                                            case 'bekliyor':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'onaylandi':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'reddedildi':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($odeme['onay_durumu']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-2"><?= htmlspecialchars($odeme['kaydeden']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Fatura Modal -->
<div id="invoiceModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden overflow-y-auto">
    <div class="bg-white rounded-lg w-full max-w-4xl mx-4 relative">
        <!-- Modal Başlık -->
        <div class="flex justify-between items-center p-4 border-b">
            <h3 class="text-lg font-semibold text-gray-800">Fatura Detayı</h3>
            <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeInvoiceModal()">
                <i class="ri-close-line text-2xl"></i>
            </button>
        </div>
        
        <!-- Modal İçerik - Fatura Detayı Burada Yüklenecek -->
        <div id="invoiceModalContent" class="p-4 min-h-[400px] max-h-[80vh] overflow-y-auto">
            <div class="flex justify-center items-center h-full">
                <i class="ri-loader-4-line animate-spin text-3xl text-primary"></i>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // openInvoiceModal fonksiyonunu global kapsamda tanımlıyoruz
    window.openInvoiceModal = function(faturaId) {
        const modal = document.getElementById('invoiceModal');
        if (!modal) return;
        
        modal.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
        
        const contentArea = document.getElementById('invoiceModalContent');
        if (contentArea) {
            contentArea.innerHTML = '<div class="flex justify-center items-center h-64"><i class="ri-loader-4-line animate-spin text-3xl text-primary"></i></div>';
            
            fetch(`fatura_detay.php?id=${faturaId}&modal=1`)
                .then(response => response.text())
                .then(data => {
                    contentArea.innerHTML = data;
                })
                .catch(error => {
                    contentArea.innerHTML = `<div class="p-4 text-red-600">Fatura detayları yüklenirken bir hata oluştu: ${error.message}</div>`;
                    console.error('Fatura detayları yüklenirken hata:', error);
                });
        }
    };
    
    window.closeInvoiceModal = function() {
        const modal = document.getElementById('invoiceModal');
        if (!modal) return;
        
        modal.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
        
        const contentArea = document.getElementById('invoiceModalContent');
        if (contentArea) {
            contentArea.innerHTML = '';
        }
    };
    
    // DataTables'ı tüm tablolara uygula (kütüphane yüklenmişse)
    if (typeof $.fn.DataTable !== 'undefined') {
        try {
            const tables = document.querySelectorAll('table');
            tables.forEach(table => {
                $(table).DataTable({
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/tr.json'
                    },
                    pageLength: 5,
                    lengthMenu: [5, 10, 25, 50],
                    dom: '<"flex items-center justify-between mb-4"lf>rt<"flex items-center justify-between mt-4"ip>'
                });
            });
        } catch (error) {
            console.warn('DataTables yüklenirken bir hata oluştu:', error);
        }
    } else {
        console.warn('DataTables kütüphanesi bulunamadı');
    }
    
    // Modal DOM elementleri var mı kontrol et ve event listener'ları ekle
    const modal = document.getElementById('invoiceModal');
    if (modal) {
        // Modal dışına tıklama ile kapatma
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeInvoiceModal();
            }
        });
    }
    
    // ESC tuşu ile kapatma
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('invoiceModal');
            if (modal && !modal.classList.contains('hidden')) {
                closeInvoiceModal();
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>

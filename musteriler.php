<?php
// musteriler.php

// Önbellek kontrolü - sayfanın her zaman taze verilerle yüklenmesini sağlar
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'includes/db.php'; 
include 'includes/header.php';

// Başarı mesajı kontrolü
$successMessage = '';
if (isset($_GET['mesaj'])) {
    switch ($_GET['mesaj']) {
        case 'eklendi':
            $successMessage = 'Müşteri başarıyla eklendi.';
            break;
        case 'guncellendi':
            $successMessage = 'Müşteri başarıyla güncellendi.';
            break;
        case 'silindi':
            $successMessage = 'Müşteri başarıyla silindi.';
            break;
    }
}

// Durum parametresini al
$durum = isset($_GET['durum']) ? $_GET['durum'] : 'aktif';

// Veritabanından müşterileri çek - alfabetik sıralama yap
try {
    // Hata ayıklama için
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    
    // Bağlantıyı test et
    $testQuery = $pdo->query("SELECT 1");
    if (!$testQuery) {
        throw new Exception("Veritabanı bağlantısı başarılı ancak sorgu çalıştırılamadı.");
    }
    
    // Filtreleme koşulunu oluştur
    $where = "";
    if ($durum === 'aktif') {
        $where = "WHERE m.aktif = 1";
    } elseif ($durum === 'pasif') {
        $where = "WHERE m.aktif = 0";
    }
    // 'tumu' seçiliyse WHERE koşulu eklenmez
    
    // SQL sorgusunu iyileştirdim - Seçilen duruma göre müşterileri getir
    $sql = "
        SELECT DISTINCT m.id, m.ad, m.soyad, m.telefon, m.adres, m.vergi_no, m.tip_id, m.musteri_kodu, 
               m.cari_bakiye, m.aktif, m.created_at, m.updated_at
        FROM musteriler m
        $where
        ORDER BY m.ad ASC, m.soyad ASC
    ";
    
    $stmt = $pdo->query($sql);
    
    if (!$stmt) {
        throw new Exception("Müşteri sorgusu çalıştırılamadı.");
    }
    
    $musteriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Tekrarlanan müşterileri temizle - daha güçlü kontrol
    $uniqueCustomers = [];
    $uniqueIds = [];
    
    foreach ($musteriler as $musteri) {
        if (!in_array($musteri['id'], $uniqueIds)) {
            $uniqueIds[] = $musteri['id'];
            $uniqueCustomers[] = $musteri;
        }
    }
    
    $musteriler = $uniqueCustomers;
    
    // Debug için
    error_log("Müşteriler (tekrarsız): " . count($musteriler) . " adet");
    
    if (empty($musteriler)) {
        // Eğer müşteri yoksa boş bir dizi oluştur
        $musteriler = [];
    } else {
        // Her müşteri için bakiye hesapla
        foreach ($musteriler as &$m) {
            try {
                // Sadece TRY cinsinden bakiyeleri hesapla
                // Satışları hesapla (faturalar tablosundan)
                $stmtSatis = $pdo->prepare("
                    SELECT COALESCE(SUM(toplam_tutar), 0) as toplam_satis 
                    FROM faturalar 
                    WHERE musteri_id = :mid AND fatura_turu = 'satis' AND para_birimi = 'TRY'
                ");
                $stmtSatis->execute([':mid' => $m['id']]);
                $toplam_satis = $stmtSatis->fetchColumn();

                // Alışları hesapla (faturalar tablosundan)
                $stmtAlis = $pdo->prepare("
                    SELECT COALESCE(SUM(toplam_tutar), 0) as toplam_alis 
                    FROM faturalar 
                    WHERE musteri_id = :mid AND fatura_turu = 'alis' AND para_birimi = 'TRY'
                ");
                $stmtAlis->execute([':mid' => $m['id']]);
                $toplam_alis = $stmtAlis->fetchColumn();

                // Tahsilatları hesapla (odeme_tahsilat tablosundan)
                $stmtTahsilat = $pdo->prepare("
                    SELECT COALESCE(SUM(tutar), 0) as toplam_tahsilat 
                    FROM odeme_tahsilat 
                    WHERE musteri_id = :mid
                ");
                $stmtTahsilat->execute([':mid' => $m['id']]);
                $toplam_tahsilat = $stmtTahsilat->fetchColumn();

                // Gerçek bakiyeyi hesapla (satışlar - alışlar - tahsilatlar)
                $m['gercek_bakiye'] = $toplam_satis - $toplam_alis - $toplam_tahsilat;
                
                // Döviz bakiyelerini de ayrı hesapla
                // USD Bakiye
                $stmtUSD = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN fatura_turu = 'satis' THEN toplam_tutar ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN fatura_turu = 'alis' THEN toplam_tutar ELSE 0 END), 0) AS usd_bakiye
                    FROM faturalar 
                    WHERE musteri_id = :musteri_id 
                      AND para_birimi = 'USD'
                ");
                $stmtUSD->execute([':musteri_id' => $m['id']]);
                $m['usd_bakiye'] = $stmtUSD->fetchColumn();
                
                // EUR Bakiye
                $stmtEUR = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN fatura_turu = 'satis' THEN toplam_tutar ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN fatura_turu = 'alis' THEN toplam_tutar ELSE 0 END), 0) AS eur_bakiye
                    FROM faturalar 
                    WHERE musteri_id = :musteri_id 
                      AND para_birimi = 'EUR'
                ");
                $stmtEUR->execute([':musteri_id' => $m['id']]);
                $m['eur_bakiye'] = $stmtEUR->fetchColumn();
                
                // GBP Bakiye
                $stmtGBP = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(CASE WHEN fatura_turu = 'satis' THEN toplam_tutar ELSE 0 END), 0) -
                        COALESCE(SUM(CASE WHEN fatura_turu = 'alis' THEN toplam_tutar ELSE 0 END), 0) AS gbp_bakiye
                    FROM faturalar 
                    WHERE musteri_id = :musteri_id 
                      AND para_birimi = 'GBP'
                ");
                $stmtGBP->execute([':musteri_id' => $m['id']]);
                $m['gbp_bakiye'] = $stmtGBP->fetchColumn();
            } catch (Exception $e) {
                // Bakiye hesaplanamadıysa varsayılan değer ata
                $m['gercek_bakiye'] = 0;
                $m['usd_bakiye'] = 0;
                $m['eur_bakiye'] = 0;
                $m['gbp_bakiye'] = 0;
            }
        }
    }
} catch (Exception $e) {
    echo "<div class='bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4'>Hata: " . $e->getMessage() . "</div>";
    $musteriler = [];
}

// Müşteri tiplerini veritabanından çek
try {
    $stmtTipler = $pdo->query("SELECT * FROM musteri_tipleri ORDER BY tip_adi");
    $musteri_tipleri = $stmtTipler->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug için
    error_log("Müşteri Tipleri: " . print_r($musteri_tipleri, true));
} catch (Exception $e) {
    error_log("Müşteri tipleri çekilirken hata: " . $e->getMessage());
    $musteri_tipleri = [];
}
?>

<style>
    :where([class^="ri-"])::before { content: "\f3c2"; }
    .search-input::-webkit-search-cancel-button { display: none; }
    input[type="number"]::-webkit-inner-spin-button,
    input[type="number"]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    
    /* Responsive tablo için eklenen stiller */
    .overflow-x-auto {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    
    .overflow-x-auto::-webkit-scrollbar {
        height: 6px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 3px;
    }
    
    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: #555;
    }
    
    /* Sayfa genişliğini sınırla ve yatay kaydırmayı engelle */
    html, body {
        max-width: 100%;
        overflow-x: hidden;
    }
</style>

<!-- Sayfa İçeriği Başlangıç -->
<div class="w-full px-0 py-2">
    <?php if ($successMessage): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 mb-3 mx-2" role="alert">
        <p><?= htmlspecialchars($successMessage) ?></p>
    </div>
    <?php endif; ?>

    <div class="flex flex-col gap-4 px-2">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2">
            <div class="w-full sm:flex-1 min-w-[250px]">
                <div class="relative">
                    <input type="search" id="searchInput" placeholder="Müşteri ara..." class="w-full h-10 pl-10 pr-4 text-sm bg-white border border-gray-200 rounded focus:outline-none focus:border-primary search-input">
                    <div class="absolute left-3 top-0 h-full flex items-center justify-center w-4">
                        <i class="ri-search-line text-gray-400"></i>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                <div class="flex items-center gap-1">
                    <span class="text-xs sm:text-sm text-gray-600">Sayfa:</span>
                    <select id="pageSize" class="h-9 px-2 text-xs sm:text-sm bg-white border border-gray-200 rounded focus:outline-none focus:border-primary cursor-pointer">
                        <option value="10">10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="1000" selected>Tümü</option>
                    </select>
                </div>
                <button 
                    onclick="window.location.href='musteri_ekle.php'" 
                    class="h-9 px-3 bg-secondary text-white rounded-button flex items-center gap-1 hover:bg-opacity-90 transition-colors cursor-pointer whitespace-nowrap text-xs sm:text-sm ml-auto">
                    <i class="ri-add-line"></i>
                    <span>Yeni Müşteri</span>
                </button>
            </div>
        </div>

        <!-- Mobil Görünüm için Kart Tasarımı -->
        <div class="block md:hidden">
            <div id="customerCards" class="grid grid-cols-1 gap-3 mx-1">
                <?php foreach ($musteriler as $musteri): 
                    // TRY bakiyesi
                    $bakiye = $musteri['gercek_bakiye'] ?? 0;
                    $bakiyeClass = $bakiye > 0 ? 'text-red-500' : ($bakiye < 0 ? 'text-green-500' : 'text-gray-900');
                    $bakiyeText = $bakiye > 0 ? number_format($bakiye, 2, ',', '.') . ' ₺ (Borçlu)' : 
                                ($bakiye < 0 ? number_format(abs($bakiye), 2, ',', '.') . ' ₺ (Alacaklı)' : '0,00 ₺');
                    
                    // Döviz bakiyeleri
                    $usdBakiye = $musteri['usd_bakiye'] ?? 0;
                    $eurBakiye = $musteri['eur_bakiye'] ?? 0;
                    $gbpBakiye = $musteri['gbp_bakiye'] ?? 0;
                ?>
                <div 
                    class="bg-white rounded-lg shadow p-3 cursor-pointer" 
                    onclick="window.location.href='musteri_detay.php?id=<?= $musteri['id'] ?>'"
                >
                    <div class="flex justify-between items-start">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                <?= htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']) ?>
                                <?php if (isset($musteri['aktif']) && $musteri['aktif'] == 0): ?>
                                    <span class="ml-1 px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded-full">Pasif</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-xs text-gray-500 mt-1">
                                Kod: <?= htmlspecialchars($musteri['musteri_kodu'] ?? '') ?>
                            </div>
                        </div>
                        <div class="<?= $bakiyeClass ?> text-xs font-medium text-right">
                            <?= $bakiyeText ?>
                            <?php if ($usdBakiye != 0): ?>
                                <div class="<?= $usdBakiye < 0 ? 'text-green-500' : ($usdBakiye > 0 ? 'text-red-500' : 'text-gray-900') ?>">
                                    <?= number_format(abs($usdBakiye), 2, ',', '.') ?> $ (<?= $usdBakiye < 0 ? 'Alacaklı' : 'Borçlu' ?>)
                                </div>
                            <?php endif; ?>
                            <?php if ($eurBakiye != 0): ?>
                                <div class="<?= $eurBakiye < 0 ? 'text-green-500' : ($eurBakiye > 0 ? 'text-red-500' : 'text-gray-900') ?>">
                                    <?= number_format(abs($eurBakiye), 2, ',', '.') ?> € (<?= $eurBakiye < 0 ? 'Alacaklı' : 'Borçlu' ?>)
                                </div>
                            <?php endif; ?>
                            <?php if ($gbpBakiye != 0): ?>
                                <div class="<?= $gbpBakiye < 0 ? 'text-green-500' : ($gbpBakiye > 0 ? 'text-red-500' : 'text-gray-900') ?>">
                                    <?= number_format(abs($gbpBakiye), 2, ',', '.') ?> £ (<?= $gbpBakiye < 0 ? 'Alacaklı' : 'Borçlu' ?>)
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-1">
                        <div class="text-xs text-gray-600">
                            <span class="font-medium">Tip:</span> 
                            <?php 
                            if (!empty($musteri['tip_id'])) {
                                $tip_bulundu = false;
                                foreach ($musteri_tipleri as $tip) {
                                    if ($tip['id'] == $musteri['tip_id']) {
                                        echo htmlspecialchars($tip['tip_adi']);
                                        $tip_bulundu = true;
                                        break;
                                    }
                                }
                                if (!$tip_bulundu) {
                                    echo 'Müşteri';
                                }
                            } else {
                                echo 'Müşteri';
                            }
                            ?>
                        </div>
                        <div class="text-xs text-gray-600">
                            <span class="font-medium">Telefon:</span> <?= htmlspecialchars($musteri['telefon'] ?: '-') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($musteriler)): ?>
                <div class="bg-white rounded-lg shadow p-4 text-center text-gray-500 text-sm">
                    Henüz müşteri kaydı bulunmuyor.
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Masaüstü Görünüm için Tablo Tasarımı -->
        <div class="hidden md:block bg-white rounded-lg shadow overflow-hidden mx-4 my-4">
            <div class="overflow-x-auto w-full">
                <table class="w-full" id="customersTable">
                    <thead>
                        <tr class="bg-gray-50">
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Müşteri Kodu</th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Müşteri Adı</th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Tip</th>
                            <th scope="col" class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Telefon</th>
                            <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Bakiye (TL)</th>
                            <th scope="col" class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">Döviz Bakiye</th>
                            <th scope="col" class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($musteriler as $musteri): 
                            $bakiye = $musteri['gercek_bakiye'] ?? 0;
                            $bakiyeClass = $bakiye > 0 ? 'text-red-500' : ($bakiye < 0 ? 'text-green-500' : 'text-gray-900');
                            $bakiyeText = $bakiye > 0 ? number_format($bakiye, 2, ',', '.') . ' ₺ (Borçlu)' : 
                                        ($bakiye < 0 ? number_format(abs($bakiye), 2, ',', '.') . ' ₺ (Alacaklı)' : '0,00 ₺');
                            
                            // Döviz bakiyeleri
                            $usdBakiye = $musteri['usd_bakiye'] ?? 0;
                            $eurBakiye = $musteri['eur_bakiye'] ?? 0;
                            $gbpBakiye = $musteri['gbp_bakiye'] ?? 0;
                        ?>
                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='musteri_detay.php?id=<?= $musteri['id'] ?>'">
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900">
                                <?= htmlspecialchars($musteri['musteri_kodu'] ?? '') ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                <div class="font-medium text-gray-900">
                                <?= htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']) ?>
                                <?php if (isset($musteri['aktif']) && $musteri['aktif'] == 0): ?>
                                    <span class="ml-1 px-1 py-0.5 bg-red-100 text-red-800 text-xs rounded-full">Pasif</span>
                                <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                <?php 
                                if (!empty($musteri['tip_id'])) {
                                    $tip_bulundu = false;
                                    foreach ($musteri_tipleri as $tip) {
                                        if ($tip['id'] == $musteri['tip_id']) {
                                            echo htmlspecialchars($tip['tip_adi']);
                                            $tip_bulundu = true;
                                            break;
                                        }
                                    }
                                    if (!$tip_bulundu) {
                                        echo 'Müşteri';
                                    }
                                } else {
                                    echo 'Müşteri';
                                }
                                ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                <?= htmlspecialchars($musteri['telefon'] ?: '-') ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-right <?= $bakiyeClass ?>">
                                <?= $bakiyeText ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-right">
                                <?php if ($usdBakiye != 0): ?>
                                    <div class="<?= $usdBakiye < 0 ? 'text-green-500' : ($usdBakiye > 0 ? 'text-red-500' : 'text-gray-900') ?>">
                                        <?= number_format(abs($usdBakiye), 2, ',', '.') ?> $
                                    </div>
                                <?php endif; ?>
                                <?php if ($eurBakiye != 0): ?>
                                    <div class="<?= $eurBakiye < 0 ? 'text-green-500' : ($eurBakiye > 0 ? 'text-red-500' : 'text-gray-900') ?>">
                                        <?= number_format(abs($eurBakiye), 2, ',', '.') ?> €
                                    </div>
                                <?php endif; ?>
                                <?php if ($gbpBakiye != 0): ?>
                                    <div class="<?= $gbpBakiye < 0 ? 'text-green-500' : ($gbpBakiye > 0 ? 'text-red-500' : 'text-gray-900') ?>">
                                        <?= number_format(abs($gbpBakiye), 2, ',', '.') ?> £
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-sm text-center">
                                <div class="flex justify-center space-x-2">
                                    <a href="musteri_detay.php?id=<?= $musteri['id'] ?>" class="text-blue-600 hover:text-blue-900">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                    <a href="musteri_duzenle.php?id=<?= $musteri['id'] ?>" class="text-indigo-600 hover:text-indigo-900">
                                        <i class="ri-edit-line"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($musteriler)): ?>
                        <tr>
                            <td colspan="5" class="px-2 py-2 text-xs sm:text-sm text-gray-500 text-center">Henüz müşteri kaydı bulunmuyor.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 px-1" id="pagination-container">
            <div class="text-xs sm:text-sm text-gray-600 mb-2 sm:mb-0" id="pagination-info">
                <?= count($musteriler) ?> kayıttan 1 - <?= min(count($musteriler), 10) ?> arasındaki kayıtlar gösteriliyor
            </div>
            <div class="flex items-center gap-1" id="pagination-controls">
                <button id="prev-page" class="h-8 px-2 text-xs sm:text-sm text-gray-600 border border-gray-200 rounded-button hover:bg-gray-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer whitespace-nowrap" disabled>Önceki</button>
                <div class="h-8 min-w-[32px] px-2 flex items-center justify-center text-xs sm:text-sm bg-primary text-white rounded-button">1</div>
                <button id="next-page" class="h-8 px-2 text-xs sm:text-sm text-gray-600 border border-gray-200 rounded-button hover:bg-gray-50 transition-colors cursor-pointer whitespace-nowrap" <?= count($musteriler) <= 10 ? 'disabled' : '' ?>>Sonraki</button>
            </div>
        </div>
    </div>
</div>
<!-- Sayfa İçeriği Bitiş -->

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('customersTable');
    const rows = table.querySelectorAll('tbody tr');
    const customerCards = document.getElementById('customerCards');
    const cardItems = customerCards ? customerCards.querySelectorAll('div[onclick]') : [];
    
    // Arama işlevi
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        // Tablo satırlarını filtrele
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mobil kartları filtrele
        if (cardItems.length > 0) {
            cardItems.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        updatePagination();
    });
    
    // Sayfalama işlevi
    const pageSize = document.getElementById('pageSize');
    const prevPage = document.getElementById('prev-page');
    const nextPage = document.getElementById('next-page');
    const paginationInfo = document.getElementById('pagination-info');
    
    let currentPage = 1;
    
    function updatePagination() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const visibleCards = Array.from(cardItems).filter(card => card.style.display !== 'none');
        const totalRows = visibleRows.length;
        const totalCards = visibleCards.length;
        const totalItems = Math.max(totalRows, totalCards);
        const totalPages = Math.ceil(totalItems / parseInt(pageSize.value));
        
        // Sayfa bilgisini güncelle
        const start = (currentPage - 1) * parseInt(pageSize.value) + 1;
        const end = Math.min(currentPage * parseInt(pageSize.value), totalItems);
        
        if (totalItems > 0) {
            // Eğer "Tümü" seçiliyse (1000 değeri)
            if (parseInt(pageSize.value) >= 1000) {
                paginationInfo.textContent = `Toplam ${totalItems} kayıt gösteriliyor`;
            } else {
                paginationInfo.textContent = `${totalItems} kayıttan ${start} - ${end} arasındaki kayıtlar gösteriliyor`;
            }
        } else {
            paginationInfo.textContent = 'Gösterilecek kayıt bulunamadı';
        }
        
        // Butonları güncelle
        prevPage.disabled = currentPage === 1;
        nextPage.disabled = currentPage === totalPages || totalPages === 0;
        
        // Görünür satırları güncelle
        visibleRows.forEach((row, index) => {
            // Eğer "Tümü" seçiliyse (1000 değeri) tüm satırları göster
            if (parseInt(pageSize.value) >= 1000) {
                row.style.display = '';
            } else if (index >= (currentPage - 1) * parseInt(pageSize.value) && index < currentPage * parseInt(pageSize.value)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
        
        // Mobil kartları güncelle
        if (visibleCards.length > 0) {
            visibleCards.forEach((card, index) => {
                if (parseInt(pageSize.value) >= 1000) {
                    card.style.display = '';
                } else if (index >= (currentPage - 1) * parseInt(pageSize.value) && index < currentPage * parseInt(pageSize.value)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }
    }
    
    // Sayfa boyutu değiştiğinde
    pageSize.addEventListener('change', function() {
        currentPage = 1;
        updatePagination();
    });
    
    // Önceki sayfa
    prevPage.addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            updatePagination();
        }
    });
    
    // Sonraki sayfa
    nextPage.addEventListener('click', function() {
        const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
        const visibleCards = Array.from(cardItems).filter(card => card.style.display !== 'none');
        const totalItems = Math.max(visibleRows.length, visibleCards.length);
        const totalPages = Math.ceil(totalItems / parseInt(pageSize.value));
        
        if (currentPage < totalPages) {
            currentPage++;
            updatePagination();
        }
    });
    
    // İlk yükleme
    updatePagination();
});
</script>

<?php include 'includes/footer.php'; ?>

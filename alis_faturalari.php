<?php
// alis_faturalari.php
require_once 'includes/db.php';
include 'includes/header.php';

// Alış faturalarını getir
$faturalar = [];
try {
    $stmt = $pdo->query("
        SELECT f.*, 
               t.ad AS tedarikci_ad, 
               t.soyad AS tedarikci_soyad,
               (SELECT SUM(fd.miktar * fd.birim_fiyat) FROM fatura_detaylari fd WHERE fd.fatura_id = f.id) AS hesaplanan_toplam
        FROM faturalar f
        LEFT JOIN musteriler t ON f.tedarikci_id = t.id
        WHERE f.fatura_turu = 'alis'
        ORDER BY f.fatura_tarihi DESC
    ");
    $faturalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}

// Toplam alış tutarını hesapla
$toplamAlis = 0;
foreach($faturalar as $fatura) {
    $toplamAlis += $fatura['toplam_tutar'];
}
?>

<div class="p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold">Alış Faturaları</h1>
        <a href="alis.php" class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200 flex items-center gap-1">
            <i class="ri-add-line"></i> Yeni Alış Faturası
        </a>
    </div>

    <!-- Özet Kartları -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Alış</p>
                    <p class="text-xl font-semibold"><?= number_format($toplamAlis, 2, ',', '.') ?> TL</p>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                    <i class="ri-shopping-basket-line text-blue-500"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Fatura</p>
                    <p class="text-xl font-semibold"><?= count($faturalar) ?></p>
                </div>
                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                    <i class="ri-file-list-3-line text-green-500"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Bu Ay</p>
                    <p class="text-xl font-semibold">
                        <?php
                        $buAy = 0;
                        $buAyBaslangic = date('Y-m-01');
                        $buAyBitis = date('Y-m-t');
                        
                        foreach($faturalar as $fatura) {
                            if($fatura['fatura_tarihi'] >= $buAyBaslangic && $fatura['fatura_tarihi'] <= $buAyBitis) {
                                $buAy += $fatura['toplam_tutar'];
                            }
                        }
                        
                        echo number_format($buAy, 2, ',', '.') . ' TL';
                        ?>
                    </p>
                </div>
                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                    <i class="ri-calendar-line text-purple-500"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Fatura Tablosu -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fatura No</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tedarikçi</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if(empty($faturalar)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-sm text-gray-500">Henüz alış faturası bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($faturalar as $fatura): ?>
                            <tr class="hover:bg-gray-50 cursor-pointer" onclick="showFaturaDetay(<?= $fatura['id'] ?>)">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    #<?= str_pad($fatura['id'], 8, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($fatura['tedarikci_ad'] . ' ' . $fatura['tedarikci_soyad']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d.m.Y', strtotime($fatura['fatura_tarihi'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= number_format($fatura['toplam_tutar'], 2, ',', '.') ?> TL
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $durumRenk = '';
                                    $durumText = '';
                                    
                                    switch($fatura['fatura_durum']) {
                                        case 'odendi':
                                            $durumRenk = 'bg-green-100 text-green-800';
                                            $durumText = 'Ödendi';
                                            break;
                                        case 'kismen_odenmis':
                                            $durumRenk = 'bg-yellow-100 text-yellow-800';
                                            $durumText = 'Kısmen Ödendi';
                                            break;
                                        default:
                                            $durumRenk = 'bg-gray-100 text-gray-800';
                                            $durumText = 'Bekliyor';
                                    }
                                    ?>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $durumRenk ?>">
                                        <?= $durumText ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Fatura Detay Modal -->
<div id="faturaDetayModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-auto">
        <div class="p-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold" id="modalTitle">Fatura Detayları</h3>
            <button onclick="closeFaturaDetayModal()" class="text-gray-500 hover:text-gray-700">
                <i class="ri-close-line text-xl"></i>
            </button>
        </div>
        <div class="p-4" id="faturaDetayContent">
            <div class="flex justify-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
        </div>
        <div class="p-4 border-t flex justify-end space-x-3" id="modalFooter">
            <button id="editFaturaBtn" class="px-4 py-2 bg-blue-50 text-primary hover:bg-blue-100 rounded-button text-sm">
                <i class="ri-edit-line mr-2"></i> Düzenle
            </button>
            <button id="deleteFaturaBtn" class="px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-button text-sm">
                <i class="ri-delete-bin-line mr-2"></i> Sil
            </button>
        </div>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div id="deleteConfirmModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
    <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Faturayı Sil</h3>
        <p class="text-gray-500 mb-6">Bu faturayı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
        <div class="flex justify-end space-x-3">
            <button 
                type="button" 
                onclick="closeDeleteConfirmModal()"
                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-button text-sm"
            >
                İptal
            </button>
            <button 
                type="button" 
                onclick="confirmDeleteFatura()"
                class="px-4 py-2 bg-red-600 text-white rounded-button text-sm"
            >
                Sil
            </button>
        </div>
    </div>
</div>

<script>
    let currentFaturaId = 0;
    
    // Fatura detay modalını göster
    function showFaturaDetay(id) {
        currentFaturaId = id;
        document.getElementById('faturaDetayModal').classList.remove('hidden');
        document.getElementById('faturaDetayContent').innerHTML = `
            <div class="flex justify-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
        `;
        
        // Düzenle butonuna link ekle
        document.getElementById('editFaturaBtn').onclick = function() {
            window.location.href = `fatura_duzenle.php?id=${id}`;
        };
        
        // Sil butonuna tıklama olayı ekle
        document.getElementById('deleteFaturaBtn').onclick = function() {
            showDeleteConfirmModal(id);
        };
        
        // AJAX ile fatura detaylarını getir
        fetch('ajax_islem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `islem=fatura_detay_getir&id=${id}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderFaturaDetay(data.fatura, data.detaylar);
            } else {
                document.getElementById('faturaDetayContent').innerHTML = `
                    <div class="bg-red-100 text-red-700 p-4 rounded">
                        ${data.message || 'Fatura detayları yüklenirken bir hata oluştu.'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('faturaDetayContent').innerHTML = `
                <div class="bg-red-100 text-red-700 p-4 rounded">
                    Fatura detayları yüklenirken bir hata oluştu.
                </div>
            `;
        });
    }
    
    // Fatura detay modalını kapat
    function closeFaturaDetayModal() {
        document.getElementById('faturaDetayModal').classList.add('hidden');
    }
    
    // Silme onay modalını göster
    function showDeleteConfirmModal(id) {
        currentFaturaId = id;
        document.getElementById('deleteConfirmModal').classList.remove('hidden');
    }
    
    // Silme onay modalını kapat
    function closeDeleteConfirmModal() {
        document.getElementById('deleteConfirmModal').classList.add('hidden');
    }
    
    // Fatura silme işlemini onayla
    function confirmDeleteFatura() {
        // AJAX ile silme işlemi
        fetch('ajax_islem.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `islem=fatura_sil&id=${currentFaturaId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Silme başarılı - sayfayı yenile
                window.location.reload();
            } else {
                // Hata durumunda alert göster
                alert('Hata: ' + data.message);
                closeDeleteConfirmModal();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
            closeDeleteConfirmModal();
        });
    }
    
    // Fatura detaylarını render et
    function renderFaturaDetay(fatura, detaylar) {
        const faturaTuru = fatura.fatura_turu === 'alis' ? 'Alış Faturası' : 'Satış Faturası';
        const firmaEtiketi = fatura.fatura_turu === 'alis' ? 'Tedarikçi' : 'Müşteri';
        const faturaNo = 'INV-' + String(fatura.id).padStart(8, '0');
        
        let html = `
            <div class="mb-4">
                <h4 class="font-semibold text-lg">${faturaTuru} Detayları</h4>
                <p class="text-sm text-gray-600">Fatura No: ${faturaNo}</p>
                <p class="text-sm text-gray-600">${firmaEtiketi}: ${fatura.firma_ad} ${fatura.firma_soyad}</p>
                <p class="text-sm text-gray-600">Tarih: ${new Date(fatura.fatura_tarihi).toLocaleDateString('tr-TR')}</p>
                ${fatura.aciklama ? `<p class="text-sm text-gray-600 mt-2">Not: ${fatura.aciklama}</p>` : ''}
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Kodu</th>
                            <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Ürün Adı</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">KDV</th>
                            <th scope="col" class="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
        `;
        
        if (detaylar.length === 0) {
            html += `
                <tr>
                    <td colspan="6" class="px-3 py-4 text-center text-sm text-gray-500">Fatura detayı bulunamadı.</td>
                </tr>
            `;
        } else {
            let subtotal = 0;
            let taxAmount = 0;
            
            detaylar.forEach(item => {
                const total = item.miktar * item.birim_fiyat;
                const tax = total * 0.18; // %18 KDV
                subtotal += total;
                taxAmount += tax;
                
                html += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 text-sm text-gray-500">${item.urun_kodu}</td>
                        <td class="px-3 py-2 text-sm text-gray-500">${item.urun_adi}</td>
                        <td class="px-3 py-2 text-sm text-gray-500 text-right">${item.miktar}</td>
                        <td class="px-3 py-2 text-sm text-gray-500 text-right">${formatCurrency(item.birim_fiyat)}</td>
                        <td class="px-3 py-2 text-sm text-gray-500 text-right">%18</td>
                        <td class="px-3 py-2 text-sm font-medium text-gray-900 text-right">${formatCurrency(total + tax)}</td>
                    </tr>
                `;
            });
            
            html += `
                    </tbody>
                </table>
            </div>
            
            <div class="flex justify-end mt-4">
                <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 border border-gray-200 rounded p-3 text-sm">
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">Ara Toplam:</span>
                        <span class="font-medium">${formatCurrency(subtotal)}</span>
                    </div>
                    <div class="flex justify-between py-1">
                        <span class="text-gray-600">KDV Tutarı:</span>
                        <span class="font-medium">${formatCurrency(taxAmount)}</span>
                    </div>
                    <div class="flex justify-between py-1 border-t border-gray-200 mt-2 pt-2">
                        <span class="text-gray-800 font-medium">Genel Toplam:</span>
                        <span class="text-primary font-semibold">${formatCurrency(subtotal + taxAmount)}</span>
                    </div>
                </div>
            </div>
            `;
        }
        
        document.getElementById('faturaDetayContent').innerHTML = html;
    }
    
    // Para formatı
    function formatCurrency(amount) {
        return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
    }
    
    // Modal dışına tıklandığında kapat
    document.getElementById('faturaDetayModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeFaturaDetayModal();
        }
    });
    
    // Silme onay modalı dışına tıklandığında kapat
    document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeDeleteConfirmModal();
        }
    });
    
    // ESC tuşu ile modalları kapat
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFaturaDetayModal();
            closeDeleteConfirmModal();
        }
    });
</script>

<?php include 'includes/footer.php'; ?> 
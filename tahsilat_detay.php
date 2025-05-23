<?php
// tahsilat_detay.php

require_once 'includes/db.php';
require_once 'guncelbakiye.php';

// Yazdırma modu kontrolü
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Yazdırma modunda değilse header'ı dahil et
if (!$print_mode) {
  include 'includes/header.php';
}

// ?id= parametresi => tahsilat_id
$tahsilat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$tahsilat_id) {
    echo "<div class='p-4 text-red-600'>Geçersiz tahsilat ID.</div>";
    if (!$print_mode) {
        include 'includes/footer.php';
    }
    exit;
}

try {
    // Veritabanından tahsilat bilgisi
    $stmt = $pdo->prepare("
        SELECT 
            o.*, 
            m.ad, m.soyad, m.adres, m.telefon, m.email, m.cari_bakiye,
            m.vergi_no,
            CONCAT('MUS', LPAD(m.id, 6, '0')) as musteri_kodu,
            k.kullanici_adi
        FROM odeme_tahsilat o
        JOIN musteriler m ON o.musteri_id = m.id
        LEFT JOIN kullanicilar k ON o.kullanici_id = k.id
        LEFT JOIN odeme_detay od ON o.id = od.odeme_id
        LEFT JOIN banka_listesi b ON od.banka_id = b.id
        WHERE o.id = :tid
    ");
    $stmt->execute([':tid'=>$tahsilat_id]);
    $tahsilat = $stmt->fetch(PDO::FETCH_ASSOC);

    if(!$tahsilat) {
        echo "<div class='p-4 text-red-600'>Tahsilat bulunamadı.</div>";
        if (!$print_mode) {
            include 'includes/footer.php';
        }
        exit;
    }

    // Çek/Senet gibi ödeme detaylarını getir
    $odemeDetay = null;
    if (in_array($tahsilat['odeme_yontemi'], ['cek', 'senet'])) {
        $stmtD = $pdo->prepare("
            SELECT od.*, bl.banka_adi
            FROM odeme_detay od
            LEFT JOIN banka_listesi bl ON od.banka_id = bl.id
            WHERE od.odeme_id = :id
        ");
        $stmtD->execute([':id' => $tahsilat_id]);
        $odemeDetay = $stmtD->fetch(PDO::FETCH_ASSOC);
    }

    // Müşteri için güncel bakiye hesapla
    $eskiBakiye = 0;
    $yeniBakiye = 0;
    
    if ($tahsilat['musteri_id']) {
        // Güncel bakiyeyi al
        $yeniBakiye = hesaplaGuncelBakiye($pdo, $tahsilat['musteri_id']);
        // Ödemeden önceki bakiyeyi hesapla (tahsilat bakiyeyi azaltır, bu yüzden tutarı ekle)
        $eskiBakiye = $yeniBakiye + $tahsilat['tutar'];
    }

} catch (PDOException $e) {
    echo "<div class='p-4 text-red-600'>Veritabanı hatası: " . $e->getMessage() . "</div>";
    if (!$print_mode) {
        include 'includes/footer.php';
    }
    exit;
}

// Tahsilat numarası formatı
$tahsilatNo = $tahsilat['evrak_no'] ?? 'THS-' . date('Y') . '-' . str_pad($tahsilat_id, 4, '0', STR_PAD_LEFT);
$odemeYontemi = $tahsilat['odeme_yontemi'] ?: 'Nakit';
$tarih = date('d.m.Y', strtotime($tahsilat['islem_tarihi'] ?? $tahsilat['created_at']));
$tutar = number_format($tahsilat['tutar'], 2, ',', '.');
$aciklama = $tahsilat['aciklama'] ?? '';

// Ödeme türlerini başlık olarak ayarla
$odemeTurleri = [
    'nakit' => 'Nakit',
    'kredi' => 'Kredi Kartı',
    'havale' => 'Havale',
    'cek' => 'Çek',
    'senet' => 'Senet'
];
$odemeTuru = $odemeTurleri[$odemeYontemi] ?? ucfirst($odemeYontemi);

// Yazdırma modunda ise 
if ($print_mode) {
    // Yazdırma şablonu
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tahsilat Makbuzu #<?= $tahsilatNo ?></title>
  <style>
    @page { 
      size: A4; 
      margin: 10mm 8mm;
    }
    
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-size: 11px;
      font-family: 'Arial', 'Helvetica', sans-serif;
    }
    
    body { 
      background-color: white;
      color: #333;
      line-height: 1.3;
    }
    
    .makbuz-container {
      width: 100%;
      max-width: 800px;
      margin: 0 auto;
      padding: 10px;
      border: 1px solid #ddd;
    }
    
    .header {
      display: flex;
      justify-content: space-between;
      border-bottom: 1px solid #ddd;
      padding-bottom: 10px;
      margin-bottom: 15px;
    }
    
    .company-name {
      font-size: 16px;
      font-weight: bold;
      color: #3176FF;
    }
    
    .company-slogan {
      font-size: 10px;
      color: #666;
    }
    
    .document-title {
      font-size: 14px;
      font-weight: bold;
      text-align: right;
    }
    
    .document-number {
      font-size: 10px;
      color: #666;
      text-align: right;
    }
    
    .document-date {
      font-size: 10px;
      color: #666;
      text-align: right;
    }
    
    .info-section {
      display: flex;
      justify-content: space-between;
      margin-bottom: 15px;
    }
    
    .info-section > div {
      width: 48%;
    }
    
    .section-title {
      font-weight: bold;
      margin-bottom: 5px;
      font-size: 10px;
      color: #555;
    }
    
    .customer-name {
      font-weight: bold;
      margin-bottom: 3px;
    }
    
    .customer-details {
      font-size: 9px;
      color: #555;
      margin-bottom: 2px;
    }
    
    .customer-balance {
      margin-top: 8px;
      padding-top: 5px;
      border-top: 1px dashed #ddd;
    }
    
    .balance-title {
      font-weight: bold;
      font-size: 9px;
      color: #555;
    }
    
    .balance-amount {
      font-weight: bold;
    }
    
    .balance-amount.negative {
      color: #e53e3e;
    }
    
    .balance-amount.positive {
      color: #38a169;
    }
    
    .payment-details {
      margin-bottom: 15px;
    }
    
    table {
      width: 100%;
      border-collapse: collapse;
    }
    
    table th, table td {
      padding: 5px;
      text-align: left;
      border: 1px solid #ddd;
    }
    
    table th {
      background-color: #f9fafb;
      font-size: 9px;
      font-weight: bold;
      color: #555;
    }
    
    .amount-column {
      text-align: right;
    }
    
    .notes-section {
      margin-bottom: 15px;
      padding: 8px;
      background-color: #f9fafb;
      border-radius: 4px;
    }
    
    .totals-section {
      margin-bottom: 20px;
    }
    
    .signature-section {
      display: flex;
      justify-content: space-between;
      margin-top: 30px;
    }
    
    .signature-box {
      width: 45%;
      text-align: center;
    }
    
    .signature-line {
      margin: 0 auto;
      width: 70%;
      border-bottom: 1px solid #000;
      margin-bottom: 5px;
    }
    
    .signature-title {
      font-size: 10px;
      color: #555;
    }
    
    .created-by {
      font-size: 8px;
      color: #777;
      text-align: center;
      margin-top: 30px;
    }
  </style>
</head>
<body>
  <div class="makbuz-container">
    <!-- Başlık -->
    <div class="header">
      <div>
        <div class="company-name">Efsane Baharat</div>
        <div class="company-slogan">Baharatlar & Kuruyemişler</div>
      </div>
      <div>
        <div class="document-title">TAHSİLAT MAKBUZU</div>
        <div class="document-number">Makbuz No: <?= htmlspecialchars($tahsilatNo) ?></div>
        <div class="document-date">Tarih: <?= htmlspecialchars($tarih) ?></div>
      </div>
    </div>
    
    <!-- Müşteri ve Bakiye Bilgileri -->
    <div class="info-section">
      <!-- Müşteri Bilgileri -->
      <div>
        <div class="section-title">Müşteri Bilgileri</div>
        <div class="customer-name"><?= htmlspecialchars($tahsilat['ad'] . ' ' . $tahsilat['soyad']) ?></div>
        <div class="customer-details"><?= htmlspecialchars($tahsilat['musteri_kodu']) ?></div>
        <?php if (!empty($tahsilat['adres'])): ?>
          <div class="customer-details"><?= htmlspecialchars($tahsilat['adres']) ?></div>
        <?php endif; ?>
        <?php if (!empty($tahsilat['telefon'])): ?>
          <div class="customer-details"><?= htmlspecialchars($tahsilat['telefon']) ?></div>
        <?php endif; ?>
        <?php if (!empty($tahsilat['email'])): ?>
          <div class="customer-details"><?= htmlspecialchars($tahsilat['email']) ?></div>
        <?php endif; ?>
      </div>
      
      <!-- Bakiye Bilgileri -->
      <div>
        <div class="section-title">Bakiye Bilgileri</div>
        <div class="customer-balance">
          <div class="balance-title">Tahsilat Öncesi Bakiye:</div>
          <div class="balance-amount <?= $eskiBakiye > 0 ? 'negative' : 'positive' ?>">
            <?= number_format(abs($eskiBakiye), 2, ',', '.') ?> ₺ 
            <?= $eskiBakiye > 0 ? '(Borçlu)' : ($eskiBakiye < 0 ? '(Alacaklı)' : '') ?>
          </div>
        </div>
        <div class="customer-balance">
          <div class="balance-title">Tahsilat Tutarı:</div>
          <div class="balance-amount"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?> ₺</div>
        </div>
        <div class="customer-balance">
          <div class="balance-title">Güncel Bakiye:</div>
          <div class="balance-amount <?= $yeniBakiye > 0 ? 'negative' : 'positive' ?>">
            <?= number_format(abs($yeniBakiye), 2, ',', '.') ?> ₺
            <?= $yeniBakiye > 0 ? '(Borçlu)' : ($yeniBakiye < 0 ? '(Alacaklı)' : '') ?>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Ödeme Bilgileri -->
    <div class="payment-details">
      <div class="section-title">Ödeme Bilgileri</div>
      <table>
        <thead>
          <tr>
            <th>Ödeme Türü</th>
            <th>Detay</th>
            <th class="amount-column">Tutar</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><?= htmlspecialchars($odemeTuru) ?></td>
            <td>
              <?php if ($tahsilat['odeme_yontemi'] === 'cek'): ?>
                <?= !empty($odemeDetay['banka_adi']) ? htmlspecialchars($odemeDetay['banka_adi']) . ' - ' : '' ?>
                <?= !empty($odemeDetay['cek_senet_no']) ? 'Çek No: ' . htmlspecialchars($odemeDetay['cek_senet_no']) : '' ?>
                <?= !empty($odemeDetay['vade_tarihi']) ? ' / Vade: ' . date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '' ?>
              <?php elseif ($tahsilat['odeme_yontemi'] === 'senet'): ?>
                <?= !empty($odemeDetay['cek_senet_no']) ? 'Senet No: ' . htmlspecialchars($odemeDetay['cek_senet_no']) : '' ?>
                <?= !empty($odemeDetay['vade_tarihi']) ? ' / Vade: ' . date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '' ?>
              <?php elseif ($tahsilat['odeme_yontemi'] === 'kredi'): ?>
                <?= !empty($odemeDetay['banka_adi']) ? htmlspecialchars($odemeDetay['banka_adi']) : 'Kredi Kartı' ?>
              <?php elseif ($tahsilat['odeme_yontemi'] === 'havale'): ?>
                <?= !empty($odemeDetay['banka_adi']) ? htmlspecialchars($odemeDetay['banka_adi']) : 'Havale/EFT' ?>
              <?php else: ?>
                Nakit Ödeme
              <?php endif; ?>
            </td>
            <td class="amount-column"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?> ₺</td>
          </tr>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="2" style="text-align: right; font-weight: bold;">Toplam Tutar:</td>
            <td class="amount-column" style="font-weight: bold;"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?> ₺</td>
          </tr>
        </tfoot>
      </table>
    </div>
    
    <!-- Açıklamalar -->
    <?php if (!empty($aciklama)): ?>
    <div class="notes-section">
      <div class="section-title">Açıklama</div>
      <div><?= nl2br(htmlspecialchars($aciklama)) ?></div>
    </div>
    <?php endif; ?>
    
    
    <!-- Oluşturan -->
    <div class="created-by">
      Bu belge <?= date('d.m.Y H:i') ?> tarihinde <?= htmlspecialchars($tahsilat['kullanici_adi'] ?? 'Sistem') ?> tarafından oluşturulmuştur.
    </div>
  </div>
</body>
</html>
<?php
    exit; // Yazdırma modunda diğer içeriği gösterme
}

// Normal mod (yazdırma modu değilse)
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tahsilat Makbuzu #<?= $tahsilatNo ?></title>
</head>
<body class="bg-gray-100">

<!-- Tüm içeriği kaplayan ana container -->
<div class="w-full p-4 sm:p-6">
    <!-- Butonlar -->  
    <div class="flex justify-between items-center mb-6 print:hidden">    
        <div class="flex space-x-2">      
            <button id="backButton" onclick="history.back()" class="flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm">
                <i class="ri-arrow-left-line mr-2"></i> Geri
            </button>    
        </div>        
        <div class="flex space-x-2">
            <!-- Düzenle Butonu -->
            <a href="tahsilat_duzenle.php?id=<?= $tahsilat_id ?>" class="flex items-center px-4 py-2 bg-blue-50 text-primary hover:bg-blue-100 rounded-button text-sm">
                <i class="ri-edit-line mr-2"></i> Düzenle
            </a>
            
            <!-- Sil Butonu -->
            <button id="deleteButton" class="flex items-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-button text-sm">
                <i class="ri-delete-bin-line mr-2"></i> Sil
            </button>
            
            <!-- Yazdır Butonu -->
            <button id="printButton" class="flex items-center px-4 py-2 bg-green-50 hover:bg-green-100 text-green-600 rounded-button text-sm">
                <i class="ri-printer-line mr-2"></i> Yazdır
            </button>
        </div>  
    </div>

    <!-- Makbuz İçeriği -->
    <div class="bg-white border border-gray-200 rounded shadow-sm mx-auto max-w-3xl p-6" id="makbuzContainer">
        <!-- Başlık -->
        <div class="flex flex-col sm:flex-row justify-between border-b pb-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-primary">Efsane Baharat</h1>
                <p class="text-gray-500 mt-1">Baharatlar & Kuruyemişler</p>
            </div>
            
            <div class="text-right mt-4 sm:mt-0">
                <h2 class="text-xl font-semibold">TAHSİLAT MAKBUZU</h2>
                <p class="text-secondary mt-1">Makbuz No: <span class="font-medium"><?= htmlspecialchars($tahsilatNo) ?></span></p>
                <p class="text-gray-500 mt-1">Tarih: <?= $tarih ?></p>
            </div>
        </div>
        
        <!-- Müşteri ve Bakiye Bilgileri -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Müşteri Bilgileri -->
            <div>
                <h3 class="font-medium text-gray-700 mb-2">Müşteri Bilgileri</h3>
                <p class="text-gray-900 font-semibold"><?= htmlspecialchars($tahsilat['ad'] . ' ' . $tahsilat['soyad']) ?></p>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($tahsilat['musteri_kodu']) ?></p>
                <?php if (!empty($tahsilat['adres'])): ?>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($tahsilat['adres']) ?></p>
                <?php endif; ?>
                <?php if (!empty($tahsilat['telefon'])): ?>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($tahsilat['telefon']) ?></p>
                <?php endif; ?>
                <?php if (!empty($tahsilat['email'])): ?>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($tahsilat['email']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Bakiye Bilgileri -->
            <div>
                <h3 class="font-medium text-gray-700 mb-2">Bakiye Bilgileri</h3>
                
                <div class="grid grid-cols-2 gap-2">
                    <p class="text-gray-600 text-sm">Tahsilat Öncesi Bakiye:</p>
                    <p class="text-sm text-right <?= $eskiBakiye > 0 ? 'text-red-600' : ($eskiBakiye < 0 ? 'text-green-600' : 'text-gray-600') ?>">
                        <?= number_format(abs($eskiBakiye), 2, ',', '.') ?> ₺ 
                        <?= $eskiBakiye > 0 ? '(Borçlu)' : ($eskiBakiye < 0 ? '(Alacaklı)' : '') ?>
                    </p>
                    
                    <p class="text-gray-600 text-sm">Tahsilat Tutarı:</p>
                    <p class="text-sm text-right"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?> ₺</p>
                    
                    <p class="text-gray-800 text-sm font-medium">Güncel Bakiye:</p>
                    <p class="text-right font-medium <?= $yeniBakiye > 0 ? 'text-red-600' : ($yeniBakiye < 0 ? 'text-green-600' : 'text-gray-600') ?>">
                        <?= number_format(abs($yeniBakiye), 2, ',', '.') ?> ₺ 
                        <?= $yeniBakiye > 0 ? '(Borçlu)' : ($yeniBakiye < 0 ? '(Alacaklı)' : '') ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Ödeme Bilgileri -->
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <h3 class="font-medium text-gray-700 mb-3">Ödeme Bilgileri</h3>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <div>
                    <p class="text-xs text-gray-500">Ödeme Türü</p>
                    <p class="font-medium"><?= htmlspecialchars($odemeTuru) ?></p>
                </div>
                
                <?php if ($tahsilat['odeme_yontemi'] === 'cek' && $odemeDetay): ?>
                <div>
                    <p class="text-xs text-gray-500">Banka</p>
                    <p><?= htmlspecialchars($odemeDetay['banka_adi'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Çek No</p>
                    <p><?= htmlspecialchars($odemeDetay['cek_senet_no'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Vade Tarihi</p>
                    <p><?= $odemeDetay['vade_tarihi'] ? date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '-' ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($tahsilat['odeme_yontemi'] === 'senet' && $odemeDetay): ?>
                <div>
                    <p class="text-xs text-gray-500">Senet No</p>
                    <p><?= htmlspecialchars($odemeDetay['cek_senet_no'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Vade Tarihi</p>
                    <p><?= $odemeDetay['vade_tarihi'] ? date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '-' ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($tahsilat['odeme_yontemi'], ['kredi', 'havale']) && $odemeDetay): ?>
                <div>
                    <p class="text-xs text-gray-500">Banka</p>
                    <p><?= htmlspecialchars($odemeDetay['banka_adi'] ?? '-') ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                <p class="font-semibold">Toplam Tahsilat Tutarı:</p>
                <p class="font-bold text-lg text-primary"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?> ₺</p>
            </div>
        </div>
        
        <!-- Açıklama -->
        <?php if (!empty($aciklama)): ?>
        <div class="mb-6">
            <h3 class="font-medium text-gray-700 mb-2">Açıklama</h3>
            <div class="bg-blue-50 p-4 rounded text-blue-700">
                <?= nl2br(htmlspecialchars($aciklama)) ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="text-center text-gray-500 text-xs mt-8 pt-4 border-t border-gray-200">
            <p>Bu belge <?= date('d.m.Y H:i') ?> tarihinde <?= htmlspecialchars($tahsilat['kullanici_adi'] ?? 'Sistem') ?> tarafından oluşturulmuştur.</p>
        </div>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg p-6 w-full max-w-md">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Tahsilatı Sil</h3>
    <p class="text-gray-500 mb-6">Bu tahsilatı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
    <div class="flex justify-end space-x-3">
      <button 
        type="button" 
        id="cancelDeleteBtn"
        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-button text-sm"
      >
        İptal
      </button>
      <button 
        type="button" 
        id="confirmDeleteBtn"
        class="px-4 py-2 bg-red-600 text-white rounded-button text-sm"
      >
        Sil
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // DOM elementlerini al
    const deleteModal = document.getElementById('deleteModal');
    const deleteButton = document.getElementById('deleteButton');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const printButton = document.getElementById('printButton');
    
    // Yazdır butonları
    if (printButton) {
        printButton.addEventListener('click', function() {
            window.open('tahsilat_detay.php?id=<?= $tahsilat_id ?>&print=1', '_blank');
        });
    }
    
    // Silme işlemleri
    if (deleteButton && deleteModal && confirmDeleteBtn && cancelDeleteBtn) {
        // Silme butonuna event listener ekle
        deleteButton.addEventListener('click', function() {
            deleteModal.classList.remove('hidden');
        });
        
        // Silme işlemi
        confirmDeleteBtn.addEventListener('click', function() {
            const formData = new FormData();
            formData.append('islem', 'tahsilat_sil');
            formData.append('id', <?= $tahsilat_id ?>);
            
            fetch('ajax_islem.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Tahsilat başarıyla silindi.');
                    window.location.href = 'tahsilat.php';
                } else {
                    alert('Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu'));
                    deleteModal.classList.add('hidden');
                }
            })
            .catch(error => {
                console.error('Silme hatası:', error);
                alert('İşlem sırasında bir hata oluştu.');
                deleteModal.classList.add('hidden');
            });
        });
        
        // İptal butonu
        cancelDeleteBtn.addEventListener('click', function() {
            deleteModal.classList.add('hidden');
        });
        
        // Modal dışına tıklandığında kapat
        deleteModal.addEventListener('click', function(e) {
            if (e.target === deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });
        
        // ESC tuşu ile modal kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !deleteModal.classList.contains('hidden')) {
                deleteModal.classList.add('hidden');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>

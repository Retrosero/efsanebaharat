<?php
// tediye_makbuz.php - Tediye makbuzu yazdırma sayfası
require_once 'includes/db.php';

// Yazdırma modu kontrolü
$print_mode = isset($_GET['print']) && $_GET['print'] == '1';

// Yazdırma modunda değilse header'ı dahil et
if (!$print_mode) {
  include 'includes/header.php';
}

$makbuz_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$makbuz_id) {
    echo "<div class='p-4 text-red-600'>Geçersiz makbuz ID.</div>";
    if (!$print_mode) {
        include 'includes/footer.php';
    }
    exit;
}

try {
    // Tediye makbuzu bilgilerini getir
    $stmtM = $pdo->prepare("
        SELECT ot.*, 
               m.ad, m.soyad, m.adres, m.telefon, m.email, m.cari_bakiye,
               CONCAT('MUS', LPAD(m.id, 6, '0')) as musteri_kodu,
               k.kullanici_adi AS kullanici_adi
        FROM odeme_tahsilat ot
        LEFT JOIN musteriler m ON ot.musteri_id = m.id
        LEFT JOIN kullanicilar k ON ot.kullanici_id = k.id
        WHERE ot.id = :id AND ot.islem_turu = 'tediye'
    ");
    $stmtM->execute([':id' => $makbuz_id]);
    $makbuz = $stmtM->fetch(PDO::FETCH_ASSOC);

    if (!$makbuz) {
        echo "<div class='p-4 text-red-600'>Makbuz bulunamadı.</div>";
        if (!$print_mode) {
            include 'includes/footer.php';
        }
        exit;
    }

    // Çek/Senet gibi ödeme detaylarını getir
    $odemeDetay = null;
    if (in_array($makbuz['odeme_turu'], ['cek', 'senet'])) {
        $stmtD = $pdo->prepare("
            SELECT od.*, bl.banka_adi
            FROM odeme_detay od
            LEFT JOIN banka_listesi bl ON od.banka_id = bl.id
            WHERE od.odeme_id = :id
        ");
        $stmtD->execute([':id' => $makbuz_id]);
        $odemeDetay = $stmtD->fetch(PDO::FETCH_ASSOC);
    }

    // Müşteri için güncel bakiye
    $eskiBakiye = 0;
    $yeniBakiye = 0;
    
    if ($makbuz['musteri_id']) {
        require_once 'guncelbakiye.php';
        // Güncel bakiyeyi al
        $yeniBakiye = hesaplaGuncelBakiye($pdo, $makbuz['musteri_id']);
        // Ödemeden önceki bakiyeyi hesapla (tediye bakiyeyi artırır, bu yüzden tutarı çıkar)
        $eskiBakiye = $yeniBakiye - $makbuz['tutar'];
    }

} catch (PDOException $e) {
    echo "<div class='p-4 text-red-600'>Veritabanı hatası: " . $e->getMessage() . "</div>";
    if (!$print_mode) {
        include 'includes/footer.php';
    }
    exit;
}

// Makbuz numarası formatı
$makbuzNo = $makbuz['evrak_no'] ?? 'TMB-' . str_pad($makbuz_id, 8, '0', STR_PAD_LEFT);

// Ödeme türlerini başlık olarak ayarla
$odemeTurleri = [
    'nakit' => 'Nakit',
    'kredi' => 'Kredi Kartı',
    'havale' => 'Havale',
    'cek' => 'Çek',
    'senet' => 'Senet'
];
$odemeTuru = $odemeTurleri[$makbuz['odeme_turu']] ?? ucfirst($makbuz['odeme_turu']);

// Yazdırma modunda ise
if ($print_mode) {
    // Yazdırma şablonu
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tediye Makbuzu #<?= $makbuzNo ?></title>
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
      margin-bottom: 3px;
    }
    
    .balance-row {
      display: flex;
      justify-content: space-between;
      font-size: 9px;
      margin-bottom: 2px;
    }
    
    .balance-positive {
      color: #047857; /* Green */
    }
    
    .balance-negative {
      color: #dc2626; /* Red */
    }
    
    .payment-section {
      background-color: #f9fafb;
      padding: 10px;
      margin-bottom: 15px;
      border-radius: 4px;
    }
    
    .payment-title {
      font-weight: bold;
      font-size: 10px;
      margin-bottom: 5px;
    }
    
    .payment-row {
      display: flex;
      justify-content: space-between;
      font-size: 9px;
      margin-bottom: 3px;
    }
    
    .payment-total {
      font-weight: bold;
      margin-top: 5px;
      padding-top: 5px;
      border-top: 1px dashed #ddd;
    }
    
    .note-section {
      background-color: #f0f9ff;
      padding: 8px;
      border-radius: 4px;
      margin-bottom: 15px;
    }
    
    .note-title {
      font-weight: bold;
      color: #1e40af;
      font-size: 10px;
      margin-bottom: 3px;
    }
    
    .note-content {
      color: #1e3a8a;
      font-size: 9px;
    }
    
    .signature-section {
      display: flex;
      justify-content: space-between;
      margin-top: 25px;
    }
    
    .signature-box {
      width: 45%;
      text-align: center;
    }
    
    .signature-line {
      margin: 0 auto;
      width: 80%;
      border-bottom: 1px solid #999;
      padding-top: 30px;
    }
    
    .signature-title {
      margin-top: 5px;
      font-size: 9px;
      color: #666;
    }
    
    .footer {
      margin-top: 20px;
      text-align: center;
      font-size: 9px;
      color: #6b7280;
      border-top: 1px solid #ddd;
      padding-top: 10px;
    }
  </style>
</head>
<body>
  <div class="makbuz-container">
    <!-- Header -->
    <div class="header">
      <div>
        <div class="company-name">Efsane Baharat</div>
        <div class="company-slogan">Baharatlar & Kuruyemişler</div>
      </div>
      <div>
        <div class="document-title">TEDİYE MAKBUZU</div>
        <div class="document-number">Makbuz No: <?= htmlspecialchars($makbuzNo) ?></div>
        <div class="document-date">Tarih: <?= date('d.m.Y', strtotime($makbuz['islem_tarihi'])) ?></div>
      </div>
    </div>
    
    <!-- Müşteri Bilgileri -->
    <div class="info-section">
      <div>
        <div class="section-title">MÜŞTERİ BİLGİLERİ</div>
        <div class="customer-name"><?= htmlspecialchars($makbuz['ad'] . ' ' . $makbuz['soyad']) ?></div>
        <div class="customer-details"><?= htmlspecialchars($makbuz['musteri_kodu']) ?></div>
        <?php if (!empty($makbuz['adres'])): ?>
        <div class="customer-details"><?= htmlspecialchars($makbuz['adres']) ?></div>
        <?php endif; ?>
        <?php if (!empty($makbuz['telefon'])): ?>
        <div class="customer-details"><?= htmlspecialchars($makbuz['telefon']) ?></div>
        <?php endif; ?>
        <?php if (!empty($makbuz['email'])): ?>
        <div class="customer-details"><?= htmlspecialchars($makbuz['email']) ?></div>
        <?php endif; ?>
      </div>
      
      <div>
        <div class="section-title">BAKİYE BİLGİLERİ</div>
        <div class="balance-row">
          <span>Tediye Öncesi Bakiye:</span>
          <span class="<?= $eskiBakiye > 0 ? 'balance-negative' : ($eskiBakiye < 0 ? 'balance-positive' : '') ?>">
            <?= number_format(abs($eskiBakiye), 2, ',', '.') ?> ₺
            <?= $eskiBakiye > 0 ? '(Borçlu)' : ($eskiBakiye < 0 ? '(Alacaklı)' : '') ?>
          </span>
        </div>
        <div class="balance-row">
          <span>Tediye Tutarı:</span>
          <span><?= number_format($makbuz['tutar'], 2, ',', '.') ?> ₺</span>
        </div>
        <div class="balance-row" style="font-weight: bold;">
          <span>Güncel Bakiye:</span>
          <span class="<?= $yeniBakiye > 0 ? 'balance-negative' : ($yeniBakiye < 0 ? 'balance-positive' : '') ?>">
            <?= number_format(abs($yeniBakiye), 2, ',', '.') ?> ₺
            <?= $yeniBakiye > 0 ? '(Borçlu)' : ($yeniBakiye < 0 ? '(Alacaklı)' : '') ?>
          </span>
        </div>
      </div>
    </div>
    
    <!-- Ödeme Bilgileri -->
    <div class="payment-section">
      <div class="payment-title">ÖDEME BİLGİLERİ</div>
      <div class="payment-row">
        <span>Ödeme Türü:</span>
        <span><?= htmlspecialchars($odemeTuru) ?></span>
      </div>
      
      <?php if ($makbuz['odeme_turu'] === 'cek' && $odemeDetay): ?>
      <div class="payment-row">
        <span>Banka:</span>
        <span><?= htmlspecialchars($odemeDetay['banka_adi'] ?? '-') ?></span>
      </div>
      <div class="payment-row">
        <span>Çek No:</span>
        <span><?= htmlspecialchars($odemeDetay['cek_senet_no'] ?? '-') ?></span>
      </div>
      <div class="payment-row">
        <span>Vade Tarihi:</span>
        <span><?= $odemeDetay['vade_tarihi'] ? date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '-' ?></span>
      </div>
      <?php endif; ?>
      
      <?php if ($makbuz['odeme_turu'] === 'senet' && $odemeDetay): ?>
      <div class="payment-row">
        <span>Senet No:</span>
        <span><?= htmlspecialchars($odemeDetay['cek_senet_no'] ?? '-') ?></span>
      </div>
      <div class="payment-row">
        <span>Vade Tarihi:</span>
        <span><?= $odemeDetay['vade_tarihi'] ? date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '-' ?></span>
      </div>
      <?php endif; ?>
      
      <?php if (in_array($makbuz['odeme_turu'], ['kredi', 'havale']) && $odemeDetay): ?>
      <div class="payment-row">
        <span>Banka:</span>
        <span><?= htmlspecialchars($odemeDetay['banka_adi'] ?? '-') ?></span>
      </div>
      <?php endif; ?>
      
      <div class="payment-row payment-total">
        <span>Toplam Ödeme Tutarı:</span>
        <span style="font-weight: bold;"><?= number_format($makbuz['tutar'], 2, ',', '.') ?> ₺</span>
      </div>
    </div>
    
    <!-- Açıklama -->
    <?php if (!empty($makbuz['aciklama'])): ?>
    <div class="note-section">
      <div class="note-title">AÇIKLAMA</div>
      <div class="note-content"><?= nl2br(htmlspecialchars($makbuz['aciklama'])) ?></div>
    </div>
    <?php endif; ?>
    

    
    <!-- Footer -->
    <div class="footer">
      <p>Bu belge <?= date('d.m.Y H:i') ?> tarihinde <?= htmlspecialchars($makbuz['kullanici_adi'] ?? 'Sistem') ?> tarafından oluşturulmuştur.</p>
    </div>
  </div>
  <script>
    window.onload = function() {
      window.print();
    }
  </script>
</body>
</html>
    <?php
    exit;
}

// Normal görüntüleme modu 
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tediye Makbuzu #<?= $makbuzNo ?></title>
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
                <h2 class="text-xl font-semibold">TEDİYE MAKBUZU</h2>
                <p class="text-secondary mt-1">Makbuz No: <span class="font-medium"><?= htmlspecialchars($makbuzNo) ?></span></p>
                <p class="text-gray-500 mt-1">Tarih: <?= date('d.m.Y', strtotime($makbuz['islem_tarihi'])) ?></p>
            </div>
        </div>
        
        <!-- Müşteri ve Bakiye Bilgileri -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Müşteri Bilgileri -->
            <div>
                <h3 class="font-medium text-gray-700 mb-2">Müşteri Bilgileri</h3>
                <p class="text-gray-900 font-semibold"><?= htmlspecialchars($makbuz['ad'] . ' ' . $makbuz['soyad']) ?></p>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($makbuz['musteri_kodu']) ?></p>
                <?php if (!empty($makbuz['adres'])): ?>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($makbuz['adres']) ?></p>
                <?php endif; ?>
                <?php if (!empty($makbuz['telefon'])): ?>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($makbuz['telefon']) ?></p>
                <?php endif; ?>
                <?php if (!empty($makbuz['email'])): ?>
                <p class="text-gray-600 text-sm"><?= htmlspecialchars($makbuz['email']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Bakiye Bilgileri -->
            <div>
                <h3 class="font-medium text-gray-700 mb-2">Bakiye Bilgileri</h3>
                
                <div class="grid grid-cols-2 gap-2">
                    <p class="text-gray-600 text-sm">Tediye Öncesi Bakiye:</p>
                    <p class="text-sm text-right <?= $eskiBakiye > 0 ? 'text-red-600' : ($eskiBakiye < 0 ? 'text-green-600' : 'text-gray-600') ?>">
                        <?= number_format(abs($eskiBakiye), 2, ',', '.') ?> ₺ 
                        <?= $eskiBakiye > 0 ? '(Borçlu)' : ($eskiBakiye < 0 ? '(Alacaklı)' : '') ?>
                    </p>
                    
                    <p class="text-gray-600 text-sm">Tediye Tutarı:</p>
                    <p class="text-sm text-right"><?= number_format($makbuz['tutar'], 2, ',', '.') ?> ₺</p>
                    
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
                
                <?php if ($makbuz['odeme_turu'] === 'cek' && $odemeDetay): ?>
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
                
                <?php if ($makbuz['odeme_turu'] === 'senet' && $odemeDetay): ?>
                <div>
                    <p class="text-xs text-gray-500">Senet No</p>
                    <p><?= htmlspecialchars($odemeDetay['cek_senet_no'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Vade Tarihi</p>
                    <p><?= $odemeDetay['vade_tarihi'] ? date('d.m.Y', strtotime($odemeDetay['vade_tarihi'])) : '-' ?></p>
                </div>
                <?php endif; ?>
                
                <?php if (in_array($makbuz['odeme_turu'], ['kredi', 'havale']) && $odemeDetay): ?>
                <div>
                    <p class="text-xs text-gray-500">Banka</p>
                    <p><?= htmlspecialchars($odemeDetay['banka_adi'] ?? '-') ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="mt-4 pt-4 border-t border-gray-200 flex justify-between items-center">
                <p class="font-semibold">Toplam Ödeme Tutarı:</p>
                <p class="font-bold text-lg text-primary"><?= number_format($makbuz['tutar'], 2, ',', '.') ?> ₺</p>
            </div>
        </div>
        
        <!-- Açıklama -->
        <?php if (!empty($makbuz['aciklama'])): ?>
        <div class="mb-6">
            <h3 class="font-medium text-gray-700 mb-2">Açıklama</h3>
            <div class="bg-blue-50 p-4 rounded text-blue-700">
                <?= nl2br(htmlspecialchars($makbuz['aciklama'])) ?>
            </div>
        </div>
        <?php endif; ?>
        

        
        <!-- Footer -->
        <div class="text-center text-gray-500 text-xs mt-8 pt-4 border-t border-gray-200">
            <p>Bu belge <?= date('d.m.Y H:i') ?> tarihinde <?= htmlspecialchars($makbuz['kullanici_adi'] ?? 'Sistem') ?> tarafından oluşturulmuştur.</p>
        </div>
    </div>
</div>

<script>
    document.getElementById('printButton').addEventListener('click', function() {
        window.open('tediye_makbuz.php?id=<?= $makbuz_id ?>&print=1', '_blank');
    });
</script>

<?php include 'includes/footer.php'; ?> 
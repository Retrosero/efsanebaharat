<?php
// musteri_detay.php

// Hata raporlamayı aktif et
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısını kontrol et
require_once 'includes/db.php';
if (!$pdo) {
    die("Veritabanı bağlantısı başarısız!");
}

// guncelbakiye.php dosyasının var olduğundan emin ol
$guncelbakiyePath = 'guncelbakiye.php';
if (!file_exists($guncelbakiyePath)) {
    die("guncelbakiye.php dosyası bulunamadı!");
}
require_once 'guncelbakiye.php'; // Yeni dosyayı içeri aktarıyorum

$isModal = isset($_GET['modal']) && $_GET['modal'] == '1';

// Header'ı kontrol et
$headerPath = 'includes/header.php';
if (!file_exists($headerPath)) {
    die("header.php dosyası bulunamadı!");
}
if (!$isModal) {
    include 'includes/header.php'; // Soldaki menü + top bar
}

// Müşteri ID kontrolü
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) {
    die("Müşteri ID belirtilmemiş!");
}

// SQL sorgusunu kontrol et
try {
$stmt = $pdo->prepare("SELECT * FROM musteriler WHERE id = :id");
$stmt->execute([':id' => $id]);
$musteri = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$musteri) {
        die("Müşteri bulunamadı!");
    }
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// Müşteri bul
$stmt = $pdo->prepare("SELECT * FROM musteriler WHERE id = :id");
$stmt->execute([':id'=>$id]);
$musteri = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$musteri){
    echo "<div class='p-4 text-red-600'>Müşteri bulunamadı.</div>";
    if (!$isModal) {
    include 'includes/footer.php';
    }
    exit;
}

// Müşteri bilgilerini değişkenlere ata
$adSoyad    = trim($musteri['ad'].' '.$musteri['soyad']);
$musteriID  = $musteri['musteri_kodu'] ?? ('MUS00'.$musteri['id']);
$telefon    = $musteri['telefon'];
$email      = $musteri['email'];
$adres      = $musteri['adres'];
$vergiNo    = $musteri['vergi_no'];
$vergiDairesi = $musteri['vergi_dairesi'];
$hesapAcilis = date('d.m.Y', strtotime($musteri['created_at']));

// Dinamik Cari Bakiye (Satış - Tahsilat)
function getMusteriCariBakiye($pdo, $musteri_id){
    // Toplam satış
    $stmtS = $pdo->prepare("
        SELECT COALESCE(SUM(toplam_tutar),0) AS toplamSatis
        FROM faturalar
        WHERE musteri_id=:mid
          AND fatura_turu='satis'
    ");
    $stmtS->execute([':mid'=>$musteri_id]);
    $rowS = $stmtS->fetch(PDO::FETCH_ASSOC);
    $toplamSatis = $rowS ? (float)$rowS['toplamSatis'] : 0;

    // Toplam tahsilat
    $stmtT = $pdo->prepare("
        SELECT COALESCE(SUM(tutar),0) AS toplamTahsilat
        FROM odeme_tahsilat
        WHERE musteri_id=:mid
    ");
    $stmtT->execute([':mid'=>$musteri_id]);
    $rowT = $stmtT->fetch(PDO::FETCH_ASSOC);
    $toplamTahsilat = $rowT ? (float)$rowT['toplamTahsilat'] : 0;

    return $toplamSatis - $toplamTahsilat;
}
$cariBakiye = getMusteriCariBakiye($pdo, $musteri['id']);
$cariBakiyeFormatted = number_format($cariBakiye, 2, ',', '.');

// Ciro bilgisi için fonksiyonlar
function getMusteriCiroYillik($pdo, $musteri_id, $yil) {
    // Satış, iade ve net değerlerini tutacak dizi
    $sonuclar = [
        'satis' => 0,
        'iade' => 0,
        'net' => 0
    ];
    
    // Satış faturaları toplamını hesapla
    $stmtSatis = $pdo->prepare("
        SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
        FROM faturalar
        WHERE musteri_id = :musteri_id
        AND YEAR(fatura_tarihi) = :yil
        AND fatura_turu = 'satis'
        AND iptal = 0
    ");
    
    $stmtSatis->execute([
        ':musteri_id' => $musteri_id,
        ':yil' => $yil
    ]);
    
    $sonuclar['satis'] = $stmtSatis->fetchColumn();
    
    // İade faturaları toplamını hesapla (iade faturalarını temsil eden bir alan varsa)
    $stmtIade = $pdo->prepare("
        SELECT COALESCE(SUM(toplam_tutar), 0) as toplam
        FROM faturalar
        WHERE musteri_id = :musteri_id
        AND YEAR(fatura_tarihi) = :yil
        AND fatura_turu = 'satis'
        AND iptal = 1
    ");
    
    $stmtIade->execute([
        ':musteri_id' => $musteri_id,
        ':yil' => $yil
    ]);
    
    $sonuclar['iade'] = $stmtIade->fetchColumn();
    
    // Net değeri hesapla
    $sonuclar['net'] = $sonuclar['satis'] - $sonuclar['iade'];
    
    return $sonuclar;
}

// Bu yıl ve geçen yıl için ciro bilgilerini al
$buYil = date('Y');
$gecenYil = $buYil - 1;

$buYilCiro = getMusteriCiroYillik($pdo, $musteri['id'], $buYil);
$gecenYilCiro = getMusteriCiroYillik($pdo, $musteri['id'], $gecenYil);

// Artış/Azalış yüzdesi hesapla
$artisYuzdesi = 0;
if (isset($gecenYilCiro['net']) && $gecenYilCiro['net'] > 0) {
    $artisYuzdesi = (($buYilCiro['net'] - $gecenYilCiro['net']) / $gecenYilCiro['net']) * 100;
}

// Başarı ve hata mesajlarını kontrol et
$updateSuccess = isset($_GET['success']) && $_GET['success'] == 1;
$updateError = isset($_GET['error']) ? $_GET['error'] : null;

// POST ile müşteri güncelle
$successMessage = '';
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $musteri_adi = trim($_POST['musteri_adi'] ?? '');
    $telefon     = trim($_POST['telefon'] ?? '');
    $adres       = trim($_POST['adres'] ?? '');
    $vergi_no    = trim($_POST['vergi_no'] ?? '');

    try {
        $stmtU = $pdo->prepare("
            UPDATE musteriler
               SET ad = :ad,
                   telefon = :tel,
                   adres = :adres,
                   vergi_no = :vn,
                   updated_at = NOW()
             WHERE id = :id
        ");
        
        $stmtU->execute([
           ':ad'    => $musteri_adi,
           ':tel'   => $telefon,
           ':adres' => $adres,
           ':vn'    => $vergi_no,
           ':id'    => $musteri['id']
        ]);

        if($stmtU->rowCount() > 0) {
            $successMessage = 'Müşteri bilgileri başarıyla güncellendi.';
            
            // Güncel müşteri bilgilerini yeniden çek
            $stmt = $pdo->prepare("SELECT * FROM musteriler WHERE id = :id");
            $stmt->execute([':id' => $musteri['id']]);
            $musteri = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $successMessage = 'Herhangi bir değişiklik yapılmadı.';
        }
    } catch(Exception $ex) {
        echo "Veritabanı hatası: " . $ex->getMessage();
    }
}

// SİPARİŞ GEÇMİŞİ (faturalar tablosu -> fatura_turu='satis')
$Siparisler = [];
try {
    $stmtF = $pdo->prepare("
        SELECT id, fatura_tarihi as tarih, toplam_tutar, odeme_durumu as fatura_durum
        FROM faturalar
        WHERE musteri_id=:mid
          AND fatura_turu='satis'
        ORDER BY fatura_tarihi DESC, id DESC
    ");
    $stmtF->execute([':mid'=>$musteri['id']]);
    $Siparisler = $stmtF->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $ex){ 
    error_log("Sipariş geçmişi çekilirken hata: " . $ex->getMessage());
}

// HESAP HAREKETLERİ (Union => Satış + Tahsilat)
$hesapHareketleri = [];
try {
    $sqlUnion = "
      SELECT 
        f.id AS rec_id,
        f.toplam_tutar AS tutar,
        'Satış' AS odeme_yontemi,
        f.aciklama,
        f.fatura_tarihi AS islem_tarihi,
        f.created_at,
        'Satis' AS tur
      FROM faturalar f
      WHERE f.fatura_turu='satis'
        AND f.musteri_id=:mid

      UNION

      SELECT
        o.id AS rec_id,
        o.tutar AS tutar,
        CASE 
          WHEN o.odeme_turu = 'nakit' THEN 'Nakit'
          WHEN o.odeme_turu = 'kredi' THEN 'Kredi Kartı'
          WHEN o.odeme_turu = 'havale' THEN 'Havale/EFT'
          WHEN o.odeme_turu = 'cek' THEN 'Çek'
          WHEN o.odeme_turu = 'senet' THEN 'Senet'
          ELSE CONCAT(UPPER(SUBSTRING(o.odeme_turu,1,1)), LOWER(SUBSTRING(o.odeme_turu,2)))
        END AS odeme_yontemi,
        o.aciklama,
        o.islem_tarihi,
        o.created_at,
        'Tahsilat' AS tur
      FROM odeme_tahsilat o
      WHERE o.musteri_id=:mid2

      ORDER BY islem_tarihi DESC, rec_id DESC
    ";
    $stmtU = $pdo->prepare($sqlUnion);
    $stmtU->execute([':mid'=>$musteri['id'], ':mid2'=>$musteri['id']]);
    $hesapHareketleri = $stmtU->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){
    error_log("Hesap hareketleri çekilirken hata: " . $e->getMessage());
}

// SATIN ALINAN ÜRÜNLER => fatura_detaylari + urunler + faturalar (fatura_turu='satis')
$SatınAlınanUrunler = [];
try {
    $sqlUrunler = "
      SELECT 
         f.fatura_tarihi AS satis_tarihi,
         d.miktar,
         d.birim_fiyat,
         d.urun_adi,
         d.urun_id
      FROM fatura_detaylari d
      JOIN faturalar f ON d.fatura_id=f.id
      WHERE f.musteri_id=:mid
        AND f.fatura_turu='satis'
      ORDER BY f.fatura_tarihi DESC, f.id DESC
    ";
    $stmtUrun = $pdo->prepare($sqlUrunler);
    $stmtUrun->execute([':mid'=>$musteri['id']]);
    $SatınAlınanUrunler = $stmtUrun->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e){
    error_log("Satın alınan ürünler çekilirken hata: " . $e->getMessage());
}
?>
<!-- Sayfa İçeriği Başlangıç -->
<div class="p-2 sm:p-4">
  <!-- Geri Butonu -->
  <button 
    onclick="history.back()" 
    class="flex items-center mb-4 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
  >
    <i class="ri-arrow-left-line mr-2"></i> Geri
  </button>

  <div class="bg-white rounded-lg shadow p-3 sm:p-6">
    <!-- Üst Bilgiler -->
    <div class="mb-4 sm:mb-6">
      <h1 class="text-xl sm:text-2xl font-bold"><?= htmlspecialchars($adSoyad) ?></h1>
      <p class="text-gray-600">Müşteri ID: #<?= htmlspecialchars($musteriID) ?></p>
    </div>

    <!-- Bilgi Kartları -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6">
      <!-- İletişim Bilgileri -->
      <div class="bg-gray-50 p-3 sm:p-4 rounded-lg">
        <h3 class="font-semibold mb-2">İletişim Bilgileri</h3>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-phone-line mr-2"></i><?= htmlspecialchars($musteri['telefon'] ?: 'Telefon belirtilmemiş') ?></p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-mail-line mr-2"></i><?= htmlspecialchars($musteri['email'] ?: 'E-posta belirtilmemiş') ?></p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-map-pin-line mr-2"></i><?= htmlspecialchars($musteri['adres'] ?: 'Adres belirtilmemiş') ?></p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-file-list-line mr-2"></i>Vergi No: <?= htmlspecialchars($musteri['vergi_no'] ?: 'Belirtilmemiş') ?></p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-building-line mr-2"></i>Vergi Dairesi: <?= htmlspecialchars($musteri['vergi_dairesi'] ?: 'Belirtilmemiş') ?></p>
      </div>
      
      <!-- Hesap Bilgileri -->
      <div class="bg-gray-50 p-3 sm:p-4 rounded-lg">
        <h3 class="font-semibold mb-2">Hesap Bilgileri</h3>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-user-3-line mr-2"></i>Müşteri Kodu: <?= $musteri['musteri_kodu'] ?? ('MUS' . str_pad($musteri['id'], 5, '0', STR_PAD_LEFT)) ?></p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-group-line mr-2"></i>Müşteri Tipi: 
          <?php 
          if (!empty($musteri['tip_id'])) {
            // Müşteri tipini veritabanından çek
            $stmtTip = $pdo->prepare("SELECT tip_adi FROM musteri_tipleri WHERE id = :tip_id");
            $stmtTip->execute([':tip_id' => $musteri['tip_id']]);
            $tip = $stmtTip->fetchColumn();
            echo htmlspecialchars($tip ?: 'Belirtilmemiş');
          } else {
            echo 'Belirtilmemiş';
          }
          ?>
        </p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-calendar-line mr-2"></i>Kayıt Tarihi: <?= date('d.m.Y', strtotime($musteri['created_at'])) ?></p>
        <p class="flex items-center text-sm sm:text-base"><i class="ri-checkbox-circle-line mr-2"></i>Durum: <span class="text-green-600">Aktif</span></p>
      </div>
      
      <!-- Cari Durum -->
      <div class="bg-primary bg-opacity-10 p-3 sm:p-4 rounded-lg">
        <h3 class="font-semibold mb-2">Cari Durum</h3>
        <div class="text-xl sm:text-2xl font-bold text-primary">
          ₺<?= $cariBakiyeFormatted ?>
        </div>
        <p class="text-xs sm:text-sm text-gray-600">Satış - Tahsilat ile hesaplanır</p>
      </div>
      
      <!-- Ciro Bilgisi (Yeni Eklenen) -->
      <div class="bg-secondary bg-opacity-10 p-3 sm:p-4 rounded-lg">
        <h3 class="font-semibold mb-2">Ciro Bilgisi</h3>
        <div class="space-y-1">
          <div class="flex justify-between items-center">
            <span class="text-sm"><?= $buYil ?> Satış:</span>
            <span class="font-medium">₺<?= number_format(isset($buYilCiro['satis']) ? $buYilCiro['satis'] : 0, 2, ',', '.') ?></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-sm"><?= $buYil ?> İade:</span>
            <span class="font-medium text-red-500">₺<?= number_format(isset($buYilCiro['iade']) ? $buYilCiro['iade'] : 0, 2, ',', '.') ?></span>
          </div>
          <div class="flex justify-between items-center border-t pt-1">
            <span class="text-sm font-medium"><?= $buYil ?> Net:</span>
            <span class="font-bold text-secondary">₺<?= number_format(isset($buYilCiro['net']) ? $buYilCiro['net'] : 0, 2, ',', '.') ?></span>
          </div>
          
          <div class="mt-2 pt-2 border-t border-gray-200">
            <div class="flex justify-between items-center">
              <span class="text-sm"><?= $gecenYil ?> Net:</span>
              <span class="font-medium">₺<?= number_format(isset($gecenYilCiro['net']) ? $gecenYilCiro['net'] : 0, 2, ',', '.') ?></span>
            </div>
            <div class="flex justify-between items-center mt-1">
              <span class="text-sm">Değişim:</span>
              <span class="font-medium <?= $artisYuzdesi >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                <?= $artisYuzdesi >= 0 ? '+' : '' ?><?= number_format($artisYuzdesi, 1, ',', '.') ?>%
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Sekmeler Başlıkları -->
    <div class="flex flex-wrap space-x-2 sm:space-x-6 border-b mb-4 sm:mb-6 overflow-x-auto pb-1">
      <button class="tab-btn tab-active px-3 py-2 text-sm sm:text-base whitespace-nowrap" data-tab="siparisler">
        Sipariş Geçmişi
      </button>
      <button class="tab-btn px-3 py-2 text-sm sm:text-base whitespace-nowrap" data-tab="hareketler">
        Hesap Hareketleri
      </button>
      <button class="tab-btn px-3 py-2 text-sm sm:text-base whitespace-nowrap" data-tab="urunler">
        Satın Alınan Ürünler
      </button>
      <button class="tab-btn px-3 py-2 text-sm sm:text-base whitespace-nowrap" data-tab="duzenle">
        Düzenle
      </button>
    </div>

    <!-- 1) Sipariş Geçmişi (Gerçek Faturalar) -->
    <div id="siparisler" class="tab-content">
      <div class="flex justify-end mb-4">
        <div class="relative">
          <button
            id="exportBtnSiparisler"
            class="bg-primary text-white px-3 sm:px-4 py-2 !rounded-button hover:bg-opacity-90 flex items-center text-sm sm:text-base"
          >
            <i class="ri-download-line mr-2"></i>Dışa Aktar
            <i class="ri-arrow-down-s-line ml-2"></i>
          </button>
          <div
            id="exportMenuSiparisler"
            class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border"
          >
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('siparislerTable', 'pdf')"
            >
              <i class="ri-file-pdf-line mr-2"></i>PDF
            </button>
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('siparislerTable', 'xlsx')"
            >
              <i class="ri-file-excel-line mr-2"></i>XLSX
            </button>
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('siparislerTable', 'png')"
            >
              <i class="ri-image-line mr-2"></i>PNG
            </button>
          </div>
        </div>
      </div>
      <div class="overflow-x-auto -mx-3 sm:mx-0">
        <table class="w-full text-sm sm:text-base" id="siparislerTable">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-2 sm:px-4 py-2 text-left">Tarih</th>
              <th class="px-2 sm:px-4 py-2 text-left">Fatura No</th>
              <th class="px-2 sm:px-4 py-2 text-left">Tutar</th>
              <th class="px-2 sm:px-4 py-2 text-left">Durum</th>
            </tr>
          </thead>
          <tbody>
            <?php if(empty($Siparisler)): ?>
              <tr><td colspan="4" class="p-4 text-gray-500">Henüz satış faturası yok.</td></tr>
            <?php else: ?>
              <?php foreach($Siparisler as $sip): ?>
            <tr class="border-b">
                  <td class="px-2 sm:px-4 py-2"><?= date('d.m.Y', strtotime($sip['tarih'])) ?></td>
              <td class="px-2 sm:px-4 py-2">
                    <a href="javascript:void(0)" onclick="showFaturaDetay(<?= $sip['id'] ?>)" class="text-blue-600 hover:underline">
                      #FAT<?= $sip['id'] ?>
                    </a>
              </td>
                  <td class="px-2 sm:px-4 py-2">₺<?= number_format($sip['toplam_tutar'],2,',','.') ?></td>
                  <td class="px-2 sm:px-4 py-2">
                    <span class="px-2 py-1 rounded-full text-xs <?= $sip['fatura_durum'] == 'odendi' ? 'bg-green-100 text-green-800' : ($sip['fatura_durum'] == 'kismen_odendi' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                      <?= $sip['fatura_durum'] == 'odendi' ? 'Ödendi' : ($sip['fatura_durum'] == 'kismen_odendi' ? 'Kısmen Ödendi' : 'Ödenmedi') ?>
                    </span>
                  </td>
            </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 2) Hesap Hareketleri -->
    <div id="hareketler" class="tab-content hidden">
      <div class="flex justify-between mb-4">
        <!-- Sol taraf: Filtreler vs. için boş bırakıldı -->
        <div></div>
        
        <!-- Sağ taraf: Export butonu -->
        <div class="relative">
          <button
            id="exportBtnHareketler"
            class="bg-primary text-white px-3 sm:px-4 py-2 !rounded-button hover:bg-opacity-90 flex items-center text-sm sm:text-base"
          >
            <i class="ri-download-line mr-2"></i>Dışa Aktar
            <i class="ri-arrow-down-s-line ml-2"></i>
          </button>
          <div id="exportMenuHareketler" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border">
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('hareketlerTable', 'pdf')"
            >
              <i class="ri-file-pdf-line mr-2"></i>PDF
            </button>
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('hareketlerTable', 'xlsx')"
            >
              <i class="ri-file-excel-line mr-2"></i>XLSX
            </button>
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('hareketlerTable', 'png')"
            >
              <i class="ri-image-line mr-2"></i>PNG
            </button>
          </div>
        </div>
      </div>
      
      <div class="overflow-x-auto -mx-3 sm:mx-0">
        <table class="min-w-full bg-white border-collapse text-sm sm:text-base" id="hareketlerTable">
          <thead>
            <tr class="bg-gray-50 border-b">
              <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
              <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
              <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evrak Tipi</th>
              <th class="px-2 sm:px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
              <th class="px-2 sm:px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php foreach($hesapHareketleri as $hareket): ?>
            <?php 
              $islemTarihi = date('d.m.Y', strtotime($hareket['islem_tarihi']));
              $tutarFormatted = number_format($hareket['tutar'], 2, ',', '.');
              $islemTuru = $hareket['tur'] === 'Satis' ? 'Satış' : 'Tahsilat';
              $tutarClass = $hareket['tur'] === 'Satis' ? 'text-red-600' : 'text-green-600';
              $tutarPrefix = $hareket['tur'] === 'Satis' ? '-' : '+';
              $detayUrl = $hareket['tur'] === 'Satis' ? "fatura_detay.php?id={$hareket['rec_id']}" : "tahsilat_detay.php?id={$hareket['rec_id']}";
            ?>
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='<?= $detayUrl ?>'">
              <td class="px-2 sm:px-4 py-2 text-sm text-gray-500"><?= $islemTarihi ?></td>
              <td class="px-2 sm:px-4 py-2 text-sm font-medium"><?= $islemTuru ?></td>
              <td class="px-2 sm:px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($hareket['odeme_yontemi']) ?></td>
              <td class="px-2 sm:px-4 py-2 text-sm text-gray-500"><?= htmlspecialchars($hareket['aciklama']) ?></td>
              <td class="px-2 sm:px-4 py-2 text-sm font-medium <?= $tutarClass ?> text-right"><?= $tutarPrefix ?> <?= $tutarFormatted ?> ₺</td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(empty($hesapHareketleri)): ?>
            <tr>
              <td colspan="4" class="px-2 sm:px-4 py-2 text-sm text-gray-500 text-center">Henüz hesap hareketi bulunmuyor.</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 3) Satın Alınan Ürünler (Gerçek Veriler) -->
    <div id="urunler" class="tab-content hidden">
      <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center mb-4 gap-2">
        <div class="relative w-full sm:w-64">
          <input
            type="text"
            placeholder="Ürün ara..."
            id="urunlerSearch"
            class="w-full px-4 py-2 pr-10 border rounded-lg focus:outline-none focus:border-primary text-sm"
          />
          <i class="ri-search-line absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>
        <div class="relative">
          <button
            id="exportBtnUrunler"
            class="bg-primary text-white px-3 sm:px-4 py-2 !rounded-button hover:bg-opacity-90 flex items-center text-sm sm:text-base"
          >
            <i class="ri-download-line mr-2"></i>Dışa Aktar
            <i class="ri-arrow-down-s-line ml-2"></i>
          </button>
          <div
            id="exportMenuUrunler"
            class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border"
          >
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('urunlerTable', 'pdf')"
            >
              <i class="ri-file-pdf-line mr-2"></i>PDF
            </button>
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('urunlerTable', 'xlsx')"
            >
              <i class="ri-file-excel-line mr-2"></i>XLSX
            </button>
            <button
              class="w-full text-left px-4 py-2 hover:bg-gray-50 text-sm"
              onclick="exportTable('urunlerTable', 'png')"
            >
              <i class="ri-image-line mr-2"></i>PNG
            </button>
          </div>
        </div>
      </div>
      <div class="overflow-x-auto -mx-3 sm:mx-0">
        <table class="w-full text-sm sm:text-base" id="urunlerTable">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-2 sm:px-4 py-2 text-left">Ürün Adı</th>
              <th class="px-2 sm:px-4 py-2 text-left">Miktar</th>
              <th class="px-2 sm:px-4 py-2 text-left">Birim Fiyat</th>
              <th class="px-2 sm:px-4 py-2 text-left">Satış Tarihi</th>
            </tr>
          </thead>
          <tbody id="urunlerTbody">
            <?php if(empty($SatınAlınanUrunler)): ?>
              <tr><td colspan="4" class="p-4 text-sm text-gray-500">Henüz ürün satın alımı yok.</td></tr>
            <?php else: ?>
              <?php foreach($SatınAlınanUrunler as $u): ?>
                <tr class="border-b">
                  <td class="px-2 sm:px-4 py-2"><?= htmlspecialchars($u['urun_adi']) ?></td>
                  <td class="px-2 sm:px-4 py-2"><?= intval($u['miktar']) ?></td>
                  <td class="px-2 sm:px-4 py-2">₺<?= number_format($u['birim_fiyat'],2,',','.') ?></td>
                  <td class="px-2 sm:px-4 py-2"><?= date('d.m.Y', strtotime($u['satis_tarihi'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 4) Düzenle -->
    <div id="duzenle" class="tab-content hidden">
      <div class="bg-white rounded-lg shadow-sm p-3 sm:p-6">
        <h3 class="text-lg font-medium text-gray-800 mb-4 sm:mb-6">Müşteri Bilgilerini Düzenle</h3>
        
        <?php if(isset($_GET['success']) && $_GET['success'] == 1): ?>
        <div id="successMessage" class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 sm:p-4 mb-4 sm:mb-6">
          <p>Müşteri bilgileri başarıyla güncellendi.</p>
        </div>
        <?php endif; ?>
        
        <?php if(isset($_GET['error'])): ?>
        <div id="errorMessage" class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 sm:p-4 mb-4 sm:mb-6">
          <p><?= htmlspecialchars($_GET['error']) ?></p>
        </div>
        <?php endif; ?>
        
        <form action="musteri_guncelle.php" method="POST">
          <input type="hidden" name="musteri_id" value="<?= $musteri['id'] ?>">
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
            <!-- Müşteri Kodu -->
            <div>
              <label for="musteri_kodu" class="block text-sm font-medium text-gray-700 mb-2">Müşteri Kodu <span class="text-red-600">*</span></label>
              <input 
                type="text" 
                id="musteri_kodu" 
                name="musteri_kodu" 
                value="<?= htmlspecialchars($musteri['musteri_kodu'] ?? ('MUS' . str_pad($musteri['id'], 5, '0', STR_PAD_LEFT))) ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base bg-gray-100"
                required
                readonly
              >
              <p class="text-xs text-gray-500 mt-1">Benzersiz müşteri kodu (değiştirilemez)</p>
            </div>
            
            <!-- Müşteri Tipi -->
            <div>
              <label for="tip_id" class="block text-sm font-medium text-gray-700 mb-2">Müşteri Tipi</label>
              <select 
                id="tip_id" 
                name="tip_id" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              >
                <option value="">Seçiniz</option>
                <?php 
                // Müşteri tiplerini veritabanından çek
                $stmtTipler = $pdo->query("SELECT * FROM musteri_tipleri ORDER BY tip_adi");
                $musteri_tipleri = $stmtTipler->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($musteri_tipleri as $tip): 
                ?>
                <option value="<?= $tip['id'] ?>" <?= ($musteri['tip_id'] == $tip['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($tip['tip_adi']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            
            <!-- Ad -->
            <div>
              <label for="ad" class="block text-sm font-medium text-gray-700 mb-2">Ad <span class="text-red-600">*</span></label>
              <input 
                type="text" 
                id="ad" 
                name="ad" 
                value="<?= htmlspecialchars($musteri['ad']) ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
                required
              >
            </div>
            
            <!-- Soyad -->
            <div>
              <label for="soyad" class="block text-sm font-medium text-gray-700 mb-2">Soyad</label>
              <input 
                type="text" 
                id="soyad" 
                name="soyad" 
                value="<?= htmlspecialchars($musteri['soyad'] ?? '') ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              >
            </div>
            
            <!-- Telefon -->
            <div>
              <label for="telefon" class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
              <input 
                type="tel" 
                id="telefon" 
                name="telefon" 
                value="<?= htmlspecialchars($musteri['telefon'] ?? '') ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              >
            </div>
            
            <!-- E-posta -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700 mb-2">E-posta</label>
              <input 
                type="email" 
                id="email" 
                name="email" 
                value="<?= htmlspecialchars($musteri['email'] ?? '') ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              >
            </div>
            
            <!-- Vergi/TC No -->
            <div>
              <label for="vergi_no" class="block text-sm font-medium text-gray-700 mb-2">Vergi/TC No</label>
              <input 
                type="text" 
                id="vergi_no" 
                name="vergi_no" 
                value="<?= htmlspecialchars($musteri['vergi_no'] ?? '') ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              >
            </div>
            
            <!-- Vergi Dairesi -->
            <div>
              <label for="vergi_dairesi" class="block text-sm font-medium text-gray-700 mb-2">Vergi Dairesi</label>
              <input 
                type="text" 
                id="vergi_dairesi" 
                name="vergi_dairesi" 
                value="<?= htmlspecialchars($musteri['vergi_dairesi'] ?? '') ?>" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              >
            </div>
            
            <!-- Adres -->
            <div class="md:col-span-2">
              <label for="adres" class="block text-sm font-medium text-gray-700 mb-2">Adres</label>
              <textarea 
                id="adres" 
                name="adres" 
                rows="3" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              ><?= htmlspecialchars($musteri['adres'] ?? '') ?></textarea>
            </div>
            
            <!-- Notlar -->
            <div class="md:col-span-2">
              <label for="notlar" class="block text-sm font-medium text-gray-700 mb-2">Notlar</label>
              <textarea 
                id="notlar" 
                name="notlar" 
                rows="3" 
                class="w-full px-3 sm:px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary text-sm sm:text-base"
              ><?= htmlspecialchars($musteri['notlar'] ?? '') ?></textarea>
            </div>
            
            <!-- Aktif/Pasif Durumu -->
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700 mb-2">Müşteri Durumu</label>
              <div class="flex items-center space-x-4">
                <label class="inline-flex items-center">
                  <input 
                    type="radio" 
                    name="aktif" 
                    value="1" 
                    <?= ($musteri['aktif'] ?? 1) == 1 ? 'checked' : '' ?> 
                    class="form-radio h-4 w-4 text-primary"
                  >
                  <span class="ml-2 text-sm text-gray-700">Aktif</span>
                </label>
                <label class="inline-flex items-center">
                  <input 
                    type="radio" 
                    name="aktif" 
                    value="0" 
                    <?= isset($musteri['aktif']) && $musteri['aktif'] == 0 ? 'checked' : '' ?> 
                    class="form-radio h-4 w-4 text-red-500"
                  >
                  <span class="ml-2 text-sm text-gray-700">Pasif</span>
                </label>
              </div>
              <p class="text-xs text-gray-500 mt-1">Pasif durumdaki müşteriler listelerde görünmez ve işlem yapılamaz.</p>
            </div>
          </div>
          
          <div class="mt-6 flex justify-end">
            <button 
              type="submit" 
              class="bg-primary hover:bg-blue-600 text-white px-4 sm:px-6 py-2 rounded-button text-sm sm:text-base"
            >
              Değişiklikleri Kaydet
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Pasif Yap Modal -->
<div id="pasifModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
  <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 w-[90%] sm:w-96 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
    <h3 class="text-lg sm:text-xl font-bold mb-4">Müşteriyi Pasif Yapmak İstediğinizden Emin misiniz?</h3>
    <p class="text-gray-600 mb-6 text-sm sm:text-base">
      Bu işlem geri alınamaz ve müşterinin hesabı pasif duruma geçecektir.
    </p>
    <div class="flex justify-end space-x-4">
      <button id="iptalBtn" class="px-3 sm:px-4 py-2 border !rounded-button hover:bg-gray-50 text-sm sm:text-base">İptal</button>
      <button id="onayBtn" class="bg-secondary text-white px-3 sm:px-4 py-2 !rounded-button hover:bg-opacity-90 text-sm sm:text-base">Onayla</button>
    </div>
  </div>
</div>

<!-- Fatura Detay Modal -->
<div id="faturaDetayModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
  <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 w-[95%] sm:w-3/4 max-w-4xl absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg sm:text-xl font-bold">Fatura Detayı</h3>
      <button onclick="closeFaturaDetay()" class="text-gray-500 hover:text-gray-700">
        <i class="ri-close-line text-xl sm:text-2xl"></i>
      </button>
    </div>
    <div id="faturaDetayContent" class="max-h-[60vh] sm:max-h-[70vh] overflow-y-auto">
      <!-- AJAX ile yüklenecek içerik -->
    </div>
  </div>
</div>

<!-- Tahsilat Detay Modal -->
<div id="tahsilatDetayModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
  <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 w-[95%] sm:w-3/4 max-w-4xl absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
    <div class="flex justify-between items-center mb-4">
      <h3 class="text-lg sm:text-xl font-bold">Tahsilat Detayı</h3>
      <button onclick="closeTahsilatDetay()" class="text-gray-500 hover:text-gray-700">
        <i class="ri-close-line text-xl sm:text-2xl"></i>
      </button>
    </div>
    <div id="tahsilatDetayContent" class="max-h-[60vh] sm:max-h-[70vh] overflow-y-auto">
      <!-- AJAX ile yüklenecek içerik -->
    </div>
  </div>
</div>

<!-- Silme Onay Modal -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
  <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 w-[90%] sm:w-96 absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2">
    <h3 class="text-lg sm:text-xl font-bold mb-4">Kaydı Silmek İstediğinizden Emin misiniz?</h3>
    <p class="text-gray-600 mb-6 text-sm sm:text-base">
      Bu işlem geri alınamaz ve tüm kayıt silinecektir.
    </p>
    <div class="flex justify-end space-x-4">
      <button
        onclick="closeDeleteModal()" 
        class="px-3 sm:px-4 py-2 border !rounded-button hover:bg-gray-50 text-sm sm:text-base"
      >
        İptal
      </button>
      <button
        onclick="confirmDelete()"
        class="bg-red-600 text-white px-3 sm:px-4 py-2 !rounded-button hover:bg-red-700 text-sm sm:text-base"
      >
        Sil
      </button>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', ()=>{
  // Sekmeler
  const tabBtns=document.querySelectorAll('.tab-btn');
  const tabContents=document.querySelectorAll('.tab-content');
  tabBtns.forEach(btn=>{
    btn.addEventListener('click',()=>{
      tabBtns.forEach(b=> b.classList.remove('tab-active'));
      btn.classList.add('tab-active');
      const tabId=btn.dataset.tab;
      tabContents.forEach(tc=>{
        if(tc.id===tabId){
          tc.classList.remove('hidden');
        } else {
          tc.classList.add('hidden');
        }
      });
    });
  });

  // Export menü
  const exportBtns=["Siparisler","Hareketler","Urunler"].map(id=> document.getElementById(`exportBtn${id}`));
  const exportMenus=["Siparisler","Hareketler","Urunler"].map(id=> document.getElementById(`exportMenu${id}`));
  exportBtns.forEach((btn,index)=>{
    btn.addEventListener('click',()=>{
      exportMenus.forEach((menu,i)=>{
        if(i===index) menu.classList.toggle('hidden');
        else menu.classList.add('hidden');
      });
    });
  });
  document.addEventListener('click',(e)=>{
    if(!e.target.closest('[id^="exportBtn"]')){
      exportMenus.forEach(m=> m.classList.add('hidden'));
    }
  });

  // Pasif Yap Modal
  const pasifModal=document.getElementById('pasifModal');
  const pasifYapBtn=document.getElementById('pasifYapBtn');
  const iptalBtn=document.getElementById('iptalBtn');
  const onayBtn=document.getElementById('onayBtn');
  if(pasifYapBtn){
    pasifYapBtn.addEventListener('click',()=>{
      pasifModal.classList.remove('hidden');
    });
  }
  if(iptalBtn){
    iptalBtn.addEventListener('click',()=>{
      pasifModal.classList.add('hidden');
    });
  }
  if(onayBtn){
    onayBtn.addEventListener('click',()=>{
      pasifModal.classList.add('hidden');
      alert("Müşteri pasif yapıldı (örnek). Burada DB güncellemesi yapabilirsiniz.");
    });
  }
  window.addEventListener('click',(e)=>{
    if(e.target===pasifModal){
      pasifModal.classList.add('hidden');
    }
  });

  // Telefon format
  const phone=document.getElementById('phone');
  if(phone){
    phone.addEventListener('input',(e)=>{
      let val=e.target.value.replace(/\D/g,'');
      if(val.length>0){
        if(val.length<=3){
          val=`(${val}`;
        } else if(val.length<=6){
          val=`(${val.slice(0,3)}) ${val.slice(3)}`;
        } else if(val.length<=8){
          val=`(${val.slice(0,3)}) ${val.slice(3,6)} ${val.slice(6)}`;
        } else {
          val=`(${val.slice(0,3)}) ${val.slice(3,6)} ${val.slice(6,8)} ${val.slice(8,10)}`;
        }
      }
      e.target.value=val;
    });
  }
});

// Export tablo (demo)
function exportTable(tableId,format){
  alert(`Tablo ID=${tableId}, format=${format} (demo). Gerçek indirme için ek JS kütüphaneleri eklemelisiniz.`);
}

function goToDetail(type, id) {
  if (type === 'Satis') {
    window.location.href = `fatura_detay.php?id=${id}`;
  } else {
    window.location.href = `tahsilat_detay.php?id=${id}`;
  }
}

let deleteType = '';
let deleteId = 0;

function editDetail(type, id) {
  if (type === 'Satis') {
    window.location.href = `fatura_duzenle.php?id=${id}`;
  } else {
    window.location.href = `tahsilat_duzenle.php?id=${id}`;
  }
}

function deleteDetail(type, id) {
  deleteType = type;
  deleteId = id;
  document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
  document.getElementById('deleteModal').classList.add('hidden');
}

function confirmDelete() {
  const islem = deleteType === 'Satis' ? 'fatura_sil' : 'tahsilat_sil';
  
  fetch('ajax_islem.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `islem=${islem}&id=${deleteId}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Silme başarılı - sayfı yenile
      location.reload();
    } else {
      // Hata durumunda alert göster
      alert('Hata: ' + data.message);
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('İşlem sırasında bir hata oluştu.');
  })
  .finally(() => {
    closeDeleteModal();
  });
}

// Modal dışına tıklandığında kapatma
document.addEventListener('click', (e) => {
  const deleteModal = document.getElementById('deleteModal');
  if (e.target === deleteModal) {
    closeDeleteModal();
  }
});

// ESC tuşu ile modalı kapatma
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeDeleteModal();
  }
});

function showFaturaDetay(faturaId) {
  const modal = document.getElementById('faturaDetayModal');
  const content = document.getElementById('faturaDetayContent');
  
  // Modal'ı göster
  modal.classList.remove('hidden');
  content.innerHTML = '<div class="text-center py-4"><i class="ri-loader-4-line animate-spin text-2xl"></i></div>';
  
  // AJAX ile fatura detayını getir
  fetch(`fatura_detay.php?id=${faturaId}&modal=1`)
    .then(response => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.text();
    })
    .then(html => {
      content.innerHTML = html;
      
      // Modal içindeki sil butonuna event listener ekle
      const deleteBtn = content.querySelector('button[onclick^="deleteDetail"]');
      if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
          const id = this.getAttribute('onclick').match(/\d+/)[0];
          deleteDetail('Satis', parseInt(id));
        });
      }
    })
    .catch(error => {
      console.error("Fatura detayı yüklenirken hata:", error);
      content.innerHTML = `<div class="text-red-600 p-4">Fatura detayı yüklenirken hata oluştu: ${error.message}</div>`;
    });
}

function closeFaturaDetay() {
  const modal = document.getElementById('faturaDetayModal');
  modal.classList.add('hidden');
}

function showTahsilatDetay(tahsilatId) {
  const modal = document.getElementById('tahsilatDetayModal');
  const content = document.getElementById('tahsilatDetayContent');
  
  // Modal'ı göster
  modal.classList.remove('hidden');
  content.innerHTML = '<div class="text-center py-4"><i class="ri-loader-4-line animate-spin text-2xl"></i></div>';
  
  // AJAX ile tahsilat detayını getir
  fetch(`tahsilat_detay.php?id=${tahsilatId}&modal=1`)
    .then(response => response.text())
    .then(html => {
      content.innerHTML = html;
    })
    .catch(error => {
      content.innerHTML = `<div class="text-red-600 p-4">Hata oluştu: ${error.message}</div>`;
    });
}

function closeTahsilatDetay() {
  const modal = document.getElementById('tahsilatDetayModal');
  modal.classList.add('hidden');
}

// Modal dışına tıklandığında kapatma
document.addEventListener('click', (e) => {
  const faturaModal = document.getElementById('faturaDetayModal');
  const tahsilatModal = document.getElementById('tahsilatDetayModal');
  
  if (e.target === faturaModal) {
    closeFaturaDetay();
  }
  if (e.target === tahsilatModal) {
    closeTahsilatDetay();
  }
});

// ESC tuşu ile modalları kapatma
document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    closeFaturaDetay();
    closeTahsilatDetay();
  }
});

// Sayfa yüklendiğinde URL'de success parametresi varsa
document.addEventListener('DOMContentLoaded', function() {
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has('success')) {
    // Sayfayı yenile (success parametresini kaldırarak)
    setTimeout(function() {
      window.location.href = 'musteri_detay.php?id=<?= $musteri['id'] ?>';
    }, 2000); // 2 saniye sonra yönlendir (başarı mesajını görmesi için)
  }
});

// Başarı ve hata mesajlarını otomatik olarak gizle
document.addEventListener('DOMContentLoaded', function() {
  const successMessage = document.getElementById('successMessage');
  const errorMessage = document.getElementById('errorMessage');
  
  if (successMessage) {
    setTimeout(function() {
      successMessage.style.transition = 'opacity 0.5s ease';
      successMessage.style.opacity = '0';
      setTimeout(function() {
        successMessage.style.display = 'none';
      }, 500);
    }, 3000); // 3 saniye sonra kaybolmaya başla
  }
  
  if (errorMessage) {
    setTimeout(function() {
      errorMessage.style.transition = 'opacity 0.5s ease';
      errorMessage.style.opacity = '0';
      setTimeout(function() {
        errorMessage.style.display = 'none';
      }, 500);
    }, 5000); // 5 saniye sonra kaybolmaya başla
  }
});
</script>


<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('toggleSidebar');
  const toggleIcon = document.getElementById('toggleIcon');
  const fullElements = document.querySelectorAll('.sidebar-full');
  const miniElements = document.querySelectorAll('.sidebar-mini');
  
  // Local storage'dan son durumu al
  const isMini = localStorage.getItem('sidebarMini') === 'true';
  if (isMini) {
    toggleSidebar();
  }
  
  toggleBtn.addEventListener('click', () => {
    toggleSidebar();
    // Son durumu kaydet
    localStorage.setItem('sidebarMini', sidebar.classList.contains('w-16'));
  });
  
  function toggleSidebar() {
    sidebar.classList.toggle('w-64');
    sidebar.classList.toggle('w-16');
    toggleIcon.classList.toggle('ri-arrow-left-s-line');
    toggleIcon.classList.toggle('ri-arrow-right-s-line');
    
    fullElements.forEach(el => el.classList.toggle('hidden'));
    miniElements.forEach(el => el.classList.toggle('hidden'));
  }
});
</script>

<style>
/* Menü küçültüldüğünde tooltip göster */
.w-16 .hover\:bg-gray-100:hover::after {
  content: attr(data-title);
  position: absolute;
  left: 100%;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0,0,0,0.8);
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  margin-left: 8px;
  z-index: 50;
}

/* Mobil uyumluluk için ek stiller */
@media (max-width: 640px) {
  .tab-btn {
    font-size: 0.875rem;
    padding: 0.5rem 0.75rem;
  }
  
  table {
    font-size: 0.875rem;
  }
  
  th, td {
    padding: 0.5rem 0.75rem;
  }
  
  .overflow-x-auto {
    margin-left: -0.75rem;
    margin-right: -0.75rem;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
  }
}
</style>
<?php
if (!$isModal) {
    include 'includes/footer.php';
}
?>

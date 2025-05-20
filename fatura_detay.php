<?php
// fatura_detay.php

// Hata ayıklama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/db.php';

// Modal modu kontrolü
$modal_mode = isset($_GET['modal']) && $_GET['modal'] == '1';

// Modal modunda değilse header'ı dahil et
if (!$modal_mode) {
include 'includes/header.php'; // Sol menü + üst bar
}

$fatura_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$fatura_id) {
    echo "<div class='p-4 text-red-600'>Geçersiz fatura ID.</div>";
    if (!$modal_mode) {
        include 'includes/footer.php';
    }
    exit;
}

try {
// Fatura bul ve müşteri detaylarını getir
$stmtF = $pdo->prepare("
  SELECT f.*, 
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.ad 
               WHEN f.fatura_turu = 'alis' THEN t.firma_adi 
         END AS firma_ad,
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.soyad 
               WHEN f.fatura_turu = 'alis' THEN '' 
         END AS firma_soyad,
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.adres
               WHEN f.fatura_turu = 'alis' THEN t.adres
         END AS adres,
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.telefon
               WHEN f.fatura_turu = 'alis' THEN t.telefon
         END AS telefon,
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.email
               WHEN f.fatura_turu = 'alis' THEN t.email
         END AS email,
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.cari_bakiye
               WHEN f.fatura_turu = 'alis' THEN NULL
         END AS cari_bakiye
  FROM faturalar f
  LEFT JOIN musteriler m ON f.musteri_id = m.id AND f.fatura_turu = 'satis'
  LEFT JOIN tedarikciler t ON f.tedarikci_id = t.id AND f.fatura_turu = 'alis'
  WHERE f.id = :fid
");
$stmtF->execute([':fid' => $fatura_id]);
$fatura = $stmtF->fetch(PDO::FETCH_ASSOC);

if (!$fatura) {
    echo "<div class='p-4 text-red-600'>Fatura bulunamadı.</div>";
        if (!$modal_mode) {
            include 'includes/footer.php';
        }
        exit;
    }
} catch (PDOException $e) {
    echo "<div class='p-4 text-red-600'>Veritabanı hatası: " . $e->getMessage() . "</div>";
    if (!$modal_mode) {
    include 'includes/footer.php';
    }
    exit;
}

try {
// Fatura detay + ürünler (sipariş notunu alıyoruz)
$stmtD = $pdo->prepare("
      SELECT d.*, u.urun_adi AS name, u.id AS urun_id, u.urun_kodu, d.urun_notu AS siparis_notu,
             d.indirim_orani, d.indirim_tutari, d.net_tutar
  FROM fatura_detaylari d
  JOIN urunler u ON d.urun_id = u.id
  WHERE d.fatura_id = :fid
");
$stmtD->execute([':fid' => $fatura_id]);
$detaylar = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($detaylar)) {
        error_log("Fatura detayları bulunamadı: Fatura ID = $fatura_id");
    } else {
        foreach ($detaylar as $detay) {
            error_log("Fatura Detay: Ürün: {$detay['name']}, İskonto Oranı: {$detay['indirim_orani']}%, İskonto Tutarı: {$detay['indirim_tutari']}, Net Tutar: {$detay['net_tutar']}");
        }
    }
} catch (PDOException $e) {
    error_log("Fatura detayları sorgusu hatası: " . $e->getMessage());
    $detaylar = [];
}

// Sipariş notunu fatura detaylarından al (ilk bulunan)
$orderNote = '';
foreach ($detaylar as $row) {
    if (!empty($row['siparis_notu'])) {
        $orderNote = $row['siparis_notu'];
        break;
    }
}

// Ürün bilgilerini hazırla
$items = [];
foreach ($detaylar as $row) {
    $items[] = [
        'code'      => !empty($row['urun_kodu']) ? $row['urun_kodu'] : 'PRD' . $row['urun_id'],
        'name'      => $row['name'],
        'quantity'  => (int)$row['miktar'],
        'unitPrice' => (float)$row['birim_fiyat']
    ];
}
$itemsJson = json_encode($items);
$faturaNo = 'INV-' . str_pad($fatura_id, 8, '0', STR_PAD_LEFT);

// Fatura türüne göre başlık ve etiketler
$faturaTuruBaslik = $fatura['fatura_turu'] == 'satis' ? 'Satış Faturası' : 'Alış Faturası';
$firmaEtiketi = $fatura['fatura_turu'] == 'satis' ? 'Müşteri' : 'Tedarikçi';

// Modal modunda ise sadece içeriği göster, tam sayfa HTML yapısını oluşturma
if ($modal_mode) {
    // Modal içeriği
    ?>
    <div id="printableArea" class="bg-white rounded-lg">
        <div class="flex justify-between items-center border-b pb-4 mb-4">
            <div>
                <h2 class="text-xl font-semibold"><?= $faturaTuruBaslik ?> #<?= htmlspecialchars($faturaNo) ?></h2>
                <p class="text-sm text-gray-500 mt-1">Tarih: <?= date('d.m.Y', strtotime($fatura['fatura_tarihi'])) ?></p>
            </div>
            
            <!-- Durum bilgisi -->
            <div>
                <span class="px-3 py-1 inline-flex text-sm font-semibold rounded-full <?php 
                    switch($fatura['onay_durumu']) {
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
                    <?= ucfirst($fatura['onay_durumu']) ?>
                </span>
            </div>
        </div>
        
        <!-- Bilgi Kartları -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <!-- Müşteri/Tedarikçi Bilgileri -->
            <div class="bg-gray-50 p-3 rounded-lg">
                <h3 class="font-medium text-gray-700 mb-2"><?= $firmaEtiketi ?> Bilgileri</h3>
                <p class="text-gray-900 font-semibold"><?= htmlspecialchars($fatura['firma_ad'] . ' ' . $fatura['firma_soyad']) ?></p>
                <?php if (!empty($fatura['adres'])): ?>
                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($fatura['adres']) ?></p>
                <?php endif; ?>
                <?php if (!empty($fatura['telefon'])): ?>
                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($fatura['telefon']) ?></p>
                <?php endif; ?>
                <?php if (!empty($fatura['email'])): ?>
                    <p class="text-gray-600 text-sm"><?= htmlspecialchars($fatura['email']) ?></p>
                <?php endif; ?>
                
                <!-- Cari Bakiye -->
                <?php if ($fatura['fatura_turu'] == 'satis' && isset($fatura['cari_bakiye'])): ?>
                <div class="mt-2 pt-2 border-t border-gray-200">
                    <p class="text-sm font-medium">
                        Cari Bakiye: 
                        <span class="<?= $fatura['cari_bakiye'] < 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= number_format($fatura['cari_bakiye'], 2, ',', '.') ?> ₺
                        </span>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Fatura Bilgileri -->
            <div class="bg-gray-50 p-3 rounded-lg">
                <h3 class="font-medium text-gray-700 mb-2">Fatura Bilgileri</h3>
                <div class="grid grid-cols-2 gap-2">
                    <p class="text-gray-600 text-sm">Alt Toplam:</p>
                    <p class="text-gray-900 text-sm text-right"><?= number_format($fatura['toplam_tutar'], 2, ',', '.') ?> ₺</p>
                    
                    <p class="text-gray-600 text-sm">İskonto:</p>
                    <p class="text-red-600 text-sm text-right">-<?= number_format($fatura['indirim_tutari'], 2, ',', '.') ?> ₺</p>
                    
                    <p class="text-gray-800 text-sm font-medium">Genel Toplam:</p>
                    <p class="text-primary text-right font-bold"><?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺</p>
                </div>
            </div>
        </div>
        
        <?php if (!empty($orderNote)): ?>
        <!-- Sipariş Notu -->
        <div class="bg-blue-50 p-3 rounded-lg mb-4">
            <h3 class="font-medium text-blue-800 mb-1 text-sm">Sipariş Notu</h3>
            <p class="text-blue-700 text-sm"><?= nl2br(htmlspecialchars($orderNote)) ?></p>
        </div>
        <?php endif; ?>
        
        <!-- Ürün Tablosu -->
        <div class="overflow-x-auto">
            <table class="min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase">Ürün</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase">Miktar</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase">Birim Fiyat</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase">Toplam</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase">İskonto</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase">Net</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $modal_genel_toplam = 0;
                    $modal_genel_iskonto = 0;
                    $sira = 1;
                    foreach ($detaylar as $d): 
                        $modal_genel_toplam += $d['toplam_fiyat'];
                        $modal_genel_iskonto += $d['indirim_tutari'];
                    ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-2 px-3 text-sm"><?= $sira++ ?></td>
                        <td class="py-2 px-3 text-sm">
                            <?= htmlspecialchars($d['name']) ?>
                            <?php if (!empty($d['urun_notu'])): ?>
                                <div class="text-xs text-blue-600 mt-1"><?= htmlspecialchars($d['urun_notu']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-3 text-sm text-right"><?= number_format($d['miktar'], 3, ',', '.') ?> <?= $d['olcum_birimi'] ?></td>
                        <td class="py-2 px-3 text-sm text-right"><?= number_format($d['birim_fiyat'], 2, ',', '.') ?> ₺</td>
                        <td class="py-2 px-3 text-sm text-right"><?= number_format($d['toplam_fiyat'], 2, ',', '.') ?> ₺</td>
                        <td class="py-2 px-3 text-sm text-right">
                            <?php if ($d['indirim_tutari'] > 0): ?>
                                <span class="text-red-600">
                                    -%<?= number_format($d['indirim_orani'], 2, ',', '.') ?>
                                    (<?= number_format($d['indirim_tutari'], 2, ',', '.') ?> ₺)
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-3 text-sm text-right font-medium"><?= number_format($d['net_tutar'], 2, ',', '.') ?> ₺</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- İşlemler -->
        <div class="mt-4 flex justify-end space-x-2">
            <a 
                href="fatura_detay.php?id=<?= $fatura_id ?>" 
                target="_blank"
                class="px-4 py-2 bg-blue-50 text-primary hover:bg-blue-100 rounded-md text-sm flex items-center"
            >
                <i class="ri-external-link-line mr-2"></i>
                Tam Ekran Görüntüle
            </a>
        </div>
    </div>
    <?php
    exit;
}

// Modal modunda değilse normal sayfa yapısını göster
?>
<?php if (!$modal_mode): ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fatura Detayı #<?= $faturaNo ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#3176FF',
            secondary: '#666666'
          },
          borderRadius: {
            'none': '0px',
            'sm': '4px',
            DEFAULT: '8px',
            'md': '12px',
            'lg': '16px',
            'xl': '20px',
            '2xl': '24px',
            '3xl': '32px',
            'full': '9999px',
            'button': '8px'
          }
        }
      }
    }
  </script>
  <style>
    @media print {
            .print\:hidden, .no-print, button, .button, input, select, .actions, .action-buttons {
        display: none !important;
      }
            body {
                background-color: white !important;
            }
            .print-only {
                display: block !important;
      }
    }
  </style>
</head>
<body class="bg-gray-100">
<?php endif; ?>

<!-- Tüm içeriği kaplayan ana container -->
<div class="w-full">
    <!-- Butonlar -->  <div class="flex justify-between items-center mb-6 print:hidden">    <div class="flex space-x-2">      <button         id="backButton"        onclick="history.back()"         class="flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"      >        <i class="ri-arrow-left-line mr-2"></i> Geri      </button>    </div>        <div class="flex space-x-2">      <!-- Düzenle Butonu -->      <a         href="fatura_duzenle.php?id=<?= $fatura_id ?>"         class="flex items-center px-4 py-2 bg-blue-50 text-primary hover:bg-blue-100 rounded-button text-sm"      >        <i class="ri-edit-line mr-2"></i> Düzenle      </a>            <!-- Sil Butonu -->      <button         type="button"        id="deleteButton"        class="flex items-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-button text-sm"      >        <i class="ri-delete-bin-line mr-2"></i> Sil      </button>            <!-- Yazdır Butonu - iyileştirilmiş -->      <button         id="printButton"        class="flex items-center px-4 py-2 bg-green-50 hover:bg-green-100 text-green-600 rounded-button text-sm"      >        <i class="ri-printer-line mr-2"></i> Yazdır      </button>            <!-- Dışa Aktar Butonu -->      <div class="relative">        <button           id="exportBtn"          class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"        >          <i class="ri-download-line mr-2"></i> Dışa Aktar        </button>        <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border border-gray-200">          <a href="#" onclick="exportAs('pdf')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">PDF olarak indir</a>          <a href="#" onclick="exportAs('xlsx')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Excel olarak indir</a>          <a href="#" onclick="exportAs('png')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Görüntü olarak indir</a>        </div>      </div>    </div>  </div>

    <!-- İçerik -->  <div class="bg-white border border-gray-200 rounded shadow-sm mx-4 mb-4" id="invoiceContainer">    <!-- Fatura Başlık Alanı -->    <div class="p-6 flex flex-col sm:flex-row justify-between border-b">      <!-- Sol taraf - Firma/Uygulama Bilgisi -->      <div class="mb-4 sm:mb-0">        <h1 class="text-2xl font-bold text-primary">Efsane Baharat</h1>        <p class="text-gray-500 mt-1">Baharatlar & Kuruyemişler</p>      </div>            <!-- Sağ taraf - Fatura Bilgisi -->      <div class="text-right">        <h2 class="text-xl font-semibold"><?= $faturaTuruBaslik ?></h2>        <p class="text-secondary mt-1">Fatura No: <span class="font-medium">#<?= htmlspecialchars($faturaNo) ?></span></p>        <p class="text-gray-500 mt-1">Tarih: <?= date('d.m.Y', strtotime($fatura['fatura_tarihi'])) ?></p>      </div>    </div>        <!-- Gönderen ve Alıcı Bilgileri -->    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6 border-b">      <!-- Gönderen/Alıcı Bilgisi -->      <div>      </div>            <!-- Müşteri/Tedarikçi Bilgisi -->      <div>        <h3 class="font-medium text-gray-700 mb-2"><?= $firmaEtiketi ?> Bilgileri</h3>        <p class="text-gray-900 font-semibold"><?= htmlspecialchars($fatura['firma_ad'] . ' ' . $fatura['firma_soyad']) ?></p>        <?php if (!empty($fatura['adres'])): ?>          <p class="text-gray-600"><?= htmlspecialchars($fatura['adres']) ?></p>        <?php endif; ?>        <?php if (!empty($fatura['telefon'])): ?>          <p class="text-gray-600"><?= htmlspecialchars($fatura['telefon']) ?></p>        <?php endif; ?>        <?php if (!empty($fatura['email'])): ?>          <p class="text-gray-600"><?= htmlspecialchars($fatura['email']) ?></p>        <?php endif; ?>      </div>    </div>        <?php if (!empty($orderNote)): ?>    <!-- Sipariş Notu -->    <div class="p-6 border-b">      <div class="bg-blue-50 p-4 rounded">        <h3 class="font-medium text-blue-800 mb-1">Sipariş Notu</h3>        <p class="text-blue-700"><?= nl2br(htmlspecialchars($orderNote)) ?></p>      </div>    </div>    <?php endif; ?>    <!-- Ürün Tablosu -->    <div class="p-6">      <!-- Arama Bölümü -->      <div class="relative mb-4 print:hidden">        <input           id="searchInput"          type="search"          placeholder="Ürün ara..."          class="w-full h-9 pl-9 pr-3 rounded bg-gray-50 border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-primary"        />        <i class="ri-search-line absolute left-6 top-1/2 -translate-y-1/2 text-gray-400"></i>      </div>            <!-- Tablo -->      <div class="overflow-x-auto">        <table class="min-w-full">          <thead>            <tr class="border-b-2 border-gray-200">              <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">#</th>              <th class="py-3 px-4 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Ürün</th>              <th class="py-3 px-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Miktar</th>              <th class="py-3 px-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Birim Fiyat</th>              <th class="py-3 px-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Toplam</th>              <th class="py-3 px-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">İskonto</th>              <th class="py-3 px-4 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Net Tutar</th>            </tr>          </thead>          <tbody>            <?php             $genel_toplam = 0;            $genel_iskonto = 0;            $sira = 1;            foreach ($detaylar as $d):                 $genel_toplam += $d['toplam_fiyat'];                $genel_iskonto += $d['indirim_tutari'];            ?>              <tr class="border-b border-gray-100 hover:bg-gray-50">                <td class="py-3 px-4 text-sm"><?= $sira++ ?></td>                <td class="py-3 px-4 text-sm">                  <?= htmlspecialchars($d['name']) ?>                  <?php if (!empty($d['urun_notu'])): ?>                    <div class="text-xs text-blue-600 mt-1"><?= htmlspecialchars($d['urun_notu']) ?></div>                  <?php endif; ?>                </td>                <td class="py-3 px-4 text-sm text-right">                  <?= number_format($d['miktar'], 3, ',', '.') ?> <?= $d['olcum_birimi'] ?>                </td>                <td class="py-3 px-4 text-sm text-right">                  <?= number_format($d['birim_fiyat'], 2, ',', '.') ?> ₺                </td>                <td class="py-3 px-4 text-sm text-right">                  <?= number_format($d['toplam_fiyat'], 2, ',', '.') ?> ₺                </td>                <td class="py-3 px-4 text-sm text-right">                  <?php if ($d['indirim_tutari'] > 0): ?>                    <span class="text-red-600">                      -%<?= number_format($d['indirim_orani'], 2, ',', '.') ?>                      (<?= number_format($d['indirim_tutari'], 2, ',', '.') ?> ₺)                    </span>                  <?php else: ?>                    -                  <?php endif; ?>                </td>                <td class="py-3 px-4 text-sm text-right font-medium">                  <?= number_format($d['net_tutar'], 2, ',', '.') ?> ₺                </td>              </tr>            <?php endforeach; ?>          </tbody>        </table>      </div>    </div>        <!-- Toplam Kısmı -->    <div class="p-6 border-t bg-gray-50">      <div class="flex justify-end">        <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4">          <div class="flex justify-between py-2">            <span class="text-gray-600">Ara Toplam:</span>            <span class="font-medium"><?= number_format($genel_toplam, 2, ',', '.') ?> ₺</span>          </div>          <div class="flex justify-between py-2">            <span class="text-gray-600">İskonto:</span>            <span class="font-medium text-red-600">-<?= number_format($genel_iskonto, 2, ',', '.') ?> ₺</span>          </div>          <div class="flex justify-between py-3 border-t border-gray-300 mt-2">            <span class="text-gray-800 font-semibold">Genel Toplam:</span>            <span class="text-primary font-bold text-lg"><?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺</span>          </div>        </div>      </div>    </div>        <!-- Alt Bilgi -->    <div class="p-6 text-center text-gray-500 border-t">      <p class="mb-1">Bu bir bilgi faturasıdır. Yasal fatura değildir.</p>      <p>Efsane Baharat Ltd. Şti. &copy; <?= date('Y') ?></p>    </div>  </div></div>  <!-- Yazdırma için stil --><style type="text/css" media="print">  @page {    size: auto;    margin: 0mm;  }  body {    background-color: #ffffff !important;    padding: 20px !important;  }  .print\:hidden {    display: none !important;  }  #invoiceContainer {    box-shadow: none !important;    border: none !important;  }  .hide-on-print {    display: none !important;  }</style>

<!-- Silme Onay Modalı -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg p-6 w-full max-w-md">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Faturayı Sil</h3>
    <p class="text-gray-500 mb-6">Bu faturayı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
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

<div 
  id="notification"
  class="fixed top-4 right-4 bg-white shadow-lg rounded-lg p-3 transform translate-x-full transition-transform duration-300 z-50 text-sm"
>
  <div class="flex items-center">
    <i class="ri-checkbox-circle-line text-green-500 text-base mr-2"></i>
    <span id="notificationText"></span>
  </div>
</div>

<script>
  const items = <?= $itemsJson ?>;
  // Fatura tablosundaki ürün notunu (common note) kullanıyoruz
  const productNote = <?= json_encode($fatura['aciklama']) ?>;

  // Sayfa yüklendiğinde çalışacak kodlar
  document.addEventListener('DOMContentLoaded', function() {
    try {
      // Silme işlemleri için gerekli değişkenler
      const fatura_id = <?= $fatura_id ?>;
      let faturaIdToDelete = 0;
      const deleteModal = document.getElementById('deleteModal');
      const deleteButton = document.getElementById('deleteButton');
      const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
      const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
      
      // DOM elementlerinin varlığını kontrol et
      if (!deleteModal || !deleteButton || !confirmDeleteBtn || !cancelDeleteBtn) {
        console.error('Silme modalı veya butonları bulunamadı');
        return;
      }
      
      // Sil butonuna event listener ekle
      deleteButton.addEventListener('click', function() {
        showDeleteModal(fatura_id);
      });
      
      // Confirm ve Cancel butonlarına olay dinleyicileri ekle
      confirmDeleteBtn.addEventListener('click', deleteFatura);
      cancelDeleteBtn.addEventListener('click', closeDeleteModal);
      
      // Modalı göster
      function showDeleteModal(id) {
        if (deleteModal) {
          faturaIdToDelete = id;
          deleteModal.classList.remove('hidden');
        }
      }
      
      // Modalı kapat
      function closeDeleteModal() {
        if (deleteModal) {
          deleteModal.classList.add('hidden');
        }
      }
      
      // Fatura sil
      function deleteFatura() {
        try {
          // AJAX ile silme işlemi
          fetch('ajax_islem.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `islem=fatura_sil&id=${faturaIdToDelete}`
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Silme başarılı - önceki sayfaya dön
              alert('Fatura başarıyla silindi.');
              window.location.href = document.referrer;
            } else {
              // Hata durumunda alert göster
              alert('Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu'));
              closeDeleteModal();
            }
          })
          .catch(error => {
            console.error('Error:', error);
            alert('İşlem sırasında bir hata oluştu.');
            closeDeleteModal();
          });
        } catch (error) {
          console.error('Silme işlemi hatası:', error);
          alert('İşlem sırasında bir hata oluştu.');
          closeDeleteModal();
        }
      }
      
      // Modal dışına tıklandığında kapatma
      if (deleteModal) {
        deleteModal.addEventListener('click', function(e) {
          if (e.target === this) {
            closeDeleteModal();
          }
        });
      }
      
      // ESC tuşu ile modalı kapatma
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closeDeleteModal();
        }
      });

      // Arama
      const searchInput = document.getElementById('searchInput');
      if (searchInput) {
        searchInput.addEventListener('input', (e) => {
          const val = e.target.value.toLowerCase();
          const filtered = items.filter(x =>
            x.name.toLowerCase().includes(val) ||
            x.code.toLowerCase().includes(val)
          );
          renderTable(filtered);
        });
      }
      
      // Dışa aktarma menüsü
      const exportBtn = document.getElementById('exportBtn');
      const exportMenu = document.getElementById('exportMenu');
      
      if (exportBtn && exportMenu) {
        exportBtn.addEventListener('click', function() {
          exportMenu.classList.toggle('hidden');
        });
        
        // Dışa aktarma menüsü dışına tıklandığında kapatma
        document.addEventListener('click', function(e) {
          if (!exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
            exportMenu.classList.add('hidden');
          }
        });
      }
      
      // Tablo ilk gösterim
      renderTable(items);
    } catch (error) {
      console.error('JavaScript hatası:', error);
    }
  });

  function formatCurrency(a) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(a);
  }

  function renderTable(data) {
    const tbody = document.getElementById('tableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    let subtotal = 0;
    data.forEach(item => {
      const total = item.quantity * item.unitPrice;
      subtotal += total;

      const tr = document.createElement('tr');
      tr.className = 'border-b border-gray-200 hover:bg-gray-50';

      tr.innerHTML = `
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700">${item.code}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700">${item.name}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700 text-right">${item.quantity}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700 text-right">${formatCurrency(item.unitPrice)}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700 text-right">${formatCurrency(total)}</td>
      `;
      tbody.appendChild(tr);
    });
    
    const subtotalEl = document.getElementById('subtotal');
    const totalEl = document.getElementById('total');
    
    if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
    if (totalEl) totalEl.textContent = formatCurrency(subtotal);
  }

  function showNotification(msg) {    const n = document.getElementById('notification');    if (!n) return;        document.getElementById('notificationText').textContent = msg;    n.classList.remove('translate-x-full');    setTimeout(() => n.classList.add('translate-x-full'), 3000);  }  function exportAs(fmt) {    const fmts = { pdf: 'PDF', xlsx: 'Excel', png: 'Görüntü' };    showNotification(`Fatura ${fmts[fmt]} olarak dışa aktarıldı (demo).`);  }

  // Yazdırma butonu için olay dinleyici
  document.getElementById('printButton').addEventListener('click', function() {
    // Sadece fatura içeriğini yazdırma
    printInvoiceOnly();
  });

  // Sadece fatura içeriğini yazdırma fonksiyonu
  function printInvoiceOnly() {
    // Sadece fatura alanının içeriğini al
    const invoiceContainer = document.getElementById('invoiceContainer');
    if (!invoiceContainer) return;
    
    // Yazdırma stilleri eklenmiş içerik oluştur
    const printContent = `
      <html>
      <head>
        <title>Fatura Yazdır</title>
        <meta charset="UTF-8">
        <style>
          @page { 
            size: A4; 
            margin: 15mm 10mm;
          }
          
          * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
          }
          
          body { 
            margin: 0; 
            padding: 0;
            font-family: 'Arial', 'Helvetica', sans-serif;
            background-color: white;
            color: #333;
            line-height: 1.5;
          }
          
          .invoice-container {
            max-width: 210mm;
            border: none !important;
            box-shadow: none !important;
            margin: 0 auto;
            padding: 0;
          }
          
          .header {
            display: flex;
            flex-direction: row;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 1rem;
          }
          
          .header-left {
            margin-bottom: 1rem;
          }
          
          .header-right {
            text-align: right;
          }
          
          .company-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3176FF;
          }
          
          .company-slogan {
            color: #6b7280;
            margin-top: 0.25rem;
          }
          
          .invoice-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
          }
          
          .invoice-number {
            color: #666666;
            margin-top: 0.25rem;
          }
          
          .invoice-date {
            color: #6b7280;
            margin-top: 0.25rem;
          }
          
          .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
          }
          
          .info-title {
            color: #4b5563;
            font-weight: 500;
            margin-bottom: 0.5rem;
          }
          
          .customer-name {
            font-weight: 600;
            color: #111827;
          }
          
          .customer-info {
            color: #4b5563;
          }
          
          .product-table {
            padding: 1rem;
          }
          
          table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1rem;
          }
          
          th {
            padding: 0.75rem 1rem;
            background-color: #f9fafb;
            font-weight: 600;
            text-align: left;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 2px solid #e5e7eb;
          }
          
          th.text-right {
            text-align: right;
          }
          
          td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
          }
          
          td.text-right {
            text-align: right;
          }
          
          .totals-section {
            background-color: #f9fafb;
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
          }
          
          .totals-container {
            display: flex;
            justify-content: flex-end;
          }
          
          .totals-table {
            width: 100%;
            max-width: 250px;
          }
          
          .total-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
          }
          
          .grand-total {
            border-top: 1px solid #e5e7eb;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
            font-weight: 600;
          }
          
          .grand-total-value {
            color: #3176FF;
            font-weight: bold;
            font-size: 1.125rem;
          }
          
          .footer {
            padding: 1rem;
            text-align: center;
            color: #6b7280;
            border-top: 1px solid #e5e7eb;
          }
          
          .customer-balance {
            font-weight: bold;
            margin-top: 0.5rem;
          }
          
          .negative-balance {
            color: #dc2626;
          }
          
          .positive-balance {
            color: #047857;
          }
          
        </style>
      </head>
      <body>
        <div class="invoice-container">
          <!-- Başlık -->
          <div class="header">
            <div class="header-left">
              <div class="company-name">Efsane Baharat</div>
              <div class="company-slogan">Baharatlar & Kuruyemişler</div>
            </div>
            <div class="header-right">
              <div class="invoice-title"><?= $faturaTuruBaslik ?></div>
              <div class="invoice-number">Fatura No: <span class="font-medium">#<?= htmlspecialchars($faturaNo) ?></span></div>
              <div class="invoice-date">Tarih: <?= date('d.m.Y', strtotime($fatura['fatura_tarihi'])) ?></div>
            </div>
          </div>
          
          <!-- Bilgi Bölümü -->
          <div class="info-section">
            <!-- Müşteri Bilgileri (Sol tarafta) -->
            <div>
              <div class="info-title"><?= $firmaEtiketi ?> Bilgileri</div>
              <div class="customer-name"><?= htmlspecialchars($fatura['firma_ad'] . ' ' . $fatura['firma_soyad']) ?></div>
              <?php if (!empty($fatura['adres'])): ?>
                <div class="customer-info"><?= htmlspecialchars($fatura['adres']) ?></div>
              <?php endif; ?>
              <?php if (!empty($fatura['telefon'])): ?>
                <div class="customer-info"><?= htmlspecialchars($fatura['telefon']) ?></div>
              <?php endif; ?>
              <?php if (!empty($fatura['email'])): ?>
                <div class="customer-info"><?= htmlspecialchars($fatura['email']) ?></div>
              <?php endif; ?>
            </div>
 
            </div>
          </div>
          
          <!-- Ürün Tablosu -->
          <div class="product-table">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>ÜRÜN</th>
                  <th class="text-right">MİKTAR</th>
                  <th class="text-right">BİRİM FİYAT</th>
                  <th class="text-right">TOPLAM</th>
                  <th class="text-right">İSKONTO</th>
                  <th class="text-right">NET TUTAR</th>
                </tr>
              </thead>
              <tbody>
                <?php 
                $sira = 1;
                foreach ($detaylar as $d): 
                ?>
                <tr>
                  <td><?= $sira++ ?></td>
                  <td>
                    <?= htmlspecialchars($d['name']) ?>
                    <?php if (!empty($d['urun_notu'])): ?>
                      <div style="font-size: 0.75rem; color: #1d4ed8; margin-top: 0.25rem;">
                        <?= htmlspecialchars($d['urun_notu']) ?>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="text-right"><?= number_format($d['miktar'], 3, ',', '.') ?> <?= $d['olcum_birimi'] ?></td>
                  <td class="text-right"><?= number_format($d['birim_fiyat'], 2, ',', '.') ?> ₺</td>
                  <td class="text-right"><?= number_format($d['toplam_fiyat'], 2, ',', '.') ?> ₺</td>
                  <td class="text-right">
                    <?php if ($d['indirim_tutari'] > 0): ?>
                      <span style="color: #dc2626;">
                        -%<?= number_format($d['indirim_orani'], 2, ',', '.') ?>
                        (<?= number_format($d['indirim_tutari'], 2, ',', '.') ?> ₺)
                      </span>
                    <?php else: ?>
                      -
                    <?php endif; ?>
                  </td>
                  <td class="text-right" style="font-weight: 500;">
                    <?= number_format($d['net_tutar'], 2, ',', '.') ?> ₺
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Toplam Kısmı -->
          <div class="totals-section">
            <div class="totals-container">
              <div class="totals-table">
                <div class="total-row">
                  <span>Ara Toplam:</span>
                  <span><?= number_format($genel_toplam, 2, ',', '.') ?> ₺</span>
                </div>
                <div class="total-row">
                  <span>İskonto:</span>
                  <span style="color: #dc2626;">-<?= number_format($genel_iskonto, 2, ',', '.') ?> ₺</span>
                </div>
                <div class="total-row grand-total">
                  <span>Genel Toplam:</span>
                  <span class="grand-total-value"><?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Alt Bilgi - Müşteri Bakiyesi -->
          <div class="footer">
            <?php if ($fatura['fatura_turu'] == 'satis' && isset($fatura['cari_bakiye'])): ?>
              <div class="customer-balance <?= $fatura['cari_bakiye'] < 0 ? 'negative-balance' : 'positive-balance' ?>">
                Müşteri Önceki Bakiyesi: <?= number_format($fatura['cari_bakiye'], 2, ',', '.') ?> ₺
              </div>
            <?php endif; ?>
          </div>
        </div>
      </body>
      </html>
    `;
    
    // Yazdırma penceresi aç
    const printWindow = window.open('', '_blank');
    if (!printWindow) {
      alert('Lütfen popup engelleyicisini devre dışı bırakın ve tekrar deneyin.');
      return;
    }
    
    printWindow.document.open();
    printWindow.document.write(printContent);
    printWindow.document.close();
    
    // Sayfanın yüklenmesini bekle ve yazdır
    printWindow.onload = function() {
      // Yazdır
      setTimeout(() => {
        printWindow.focus();
        printWindow.print();
      }, 300);
    };
  }
</script>

<?php include 'includes/footer.php'; ?>



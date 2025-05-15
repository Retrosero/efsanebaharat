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
// Fatura bul
$stmtF = $pdo->prepare("
  SELECT f.*, 
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.ad 
               WHEN f.fatura_turu = 'alis' THEN t.firma_adi 
         END AS firma_ad,
         CASE 
           WHEN f.fatura_turu = 'satis' THEN m.soyad 
               WHEN f.fatura_turu = 'alis' THEN '' 
         END AS firma_soyad
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

// KDV sabit 18
$items = [];
foreach ($detaylar as $row) {
    $items[] = [
        'code'      => !empty($row['urun_kodu']) ? $row['urun_kodu'] : 'PRD' . $row['urun_id'],
        'name'      => $row['name'],
        'quantity'  => (int)$row['miktar'],
        'unitPrice' => (float)$row['birim_fiyat'],
        'tax'       => 18
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
    <div class="bg-white rounded-lg p-4">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold"><?= $faturaTuruBaslik ?> #<?= htmlspecialchars($faturaNo) ?></h2>
            <div class="text-sm text-gray-500">
                <?= date('d.m.Y', strtotime($fatura['fatura_tarihi'])) ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <h3 class="font-medium text-gray-700"><?= $firmaEtiketi ?> Bilgileri</h3>
                <p class="text-gray-600"><?= htmlspecialchars($fatura['firma_ad'] . ' ' . $fatura['firma_soyad']) ?></p>
            </div>
            <div>
                <h3 class="font-medium text-gray-700">Fatura Bilgileri</h3>
                <p class="text-gray-600">Toplam: <?= number_format($fatura['toplam_tutar'], 2, ',', '.') ?> ₺</p>
                <?php if ($fatura['indirim_tutari'] > 0): ?>
                    <p class="text-red-600">İskonto: -<?= number_format($fatura['indirim_tutari'], 2, ',', '.') ?> ₺</p>
                <?php endif; ?>
                <p class="text-gray-600">Net Toplam: <?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺</p>
            </div>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full bg-white">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İskonto</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">KDV</th>
                        <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Tutar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $modal_genel_toplam = 0;
                    $modal_genel_iskonto = 0;
                    $modal_genel_kdv = 0;
                    foreach ($detaylar as $d): 
                        $modal_genel_toplam += $d['toplam_fiyat'];
                        $modal_genel_iskonto += $d['indirim_tutari'];
                        $modal_genel_kdv += $d['kdv_tutari'];
                    ?>
                    <tr class="border-b">
                        <td class="py-2 px-3 text-sm"><?= htmlspecialchars($d['name']) ?></td>
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
                        <td class="py-2 px-3 text-sm text-right">
                            %<?= number_format($d['kdv_orani'], 0) ?>
                            (<?= number_format($d['kdv_tutari'], 2, ',', '.') ?> ₺)
                        </td>
                        <td class="py-2 px-3 text-sm text-right font-medium"><?= number_format($d['net_tutar'], 2, ',', '.') ?> ₺</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="bg-gray-50 font-medium">
                        <td colspan="3" class="py-2 px-3 text-sm text-right">Ara Toplam:</td>
                        <td class="py-2 px-3 text-sm text-right"><?= number_format($modal_genel_toplam, 2, ',', '.') ?> ₺</td>
                        <td class="py-2 px-3 text-sm text-right text-red-600">
                            <?php if ($modal_genel_iskonto > 0): ?>
                                -<?= number_format($modal_genel_iskonto, 2, ',', '.') ?> ₺
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="py-2 px-3 text-sm text-right"><?= number_format($modal_genel_kdv, 2, ',', '.') ?> ₺</td>
                        <td class="py-2 px-3 text-sm text-right"><?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺</td>
                    </tr>
                </tfoot>
            </table>
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
  <!-- Butonlar -->
  <div class="flex justify-between items-center mb-6">
    <div class="flex space-x-2">
      <button 
        id="backButton"
        onclick="history.back()" 
        class="flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
      >
        <i class="ri-arrow-left-line mr-2"></i> Geri
      </button>
    </div>
    
    <div class="flex space-x-2">
      <!-- Düzenle Butonu -->
      <a 
        href="fatura_duzenle.php?id=<?= $fatura_id ?>" 
        class="flex items-center px-4 py-2 bg-blue-50 text-primary hover:bg-blue-100 rounded-button text-sm"
      >
        <i class="ri-edit-line mr-2"></i> Düzenle
      </a>
      
      <!-- Sil Butonu -->
      <button 
        type="button"
        onclick="showDeleteModal(<?= $fatura_id ?>)"
        class="flex items-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-button text-sm"
      >
        <i class="ri-delete-bin-line mr-2"></i> Sil
      </button>
      
      <!-- Yazdır Butonu -->
      <button 
        onclick="window.print()" 
        class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
      >
        <i class="ri-printer-line mr-2"></i> Yazdır
      </button>
      
      <!-- Dışa Aktar Butonu -->
      <div class="relative">
        <button 
          id="exportBtn"
          class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
        >
          <i class="ri-download-line mr-2"></i> Dışa Aktar
        </button>
        <div id="exportMenu" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg z-10 border border-gray-200">
          <a href="#" onclick="exportAs('pdf')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">PDF olarak indir</a>
          <a href="#" onclick="exportAs('xlsx')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Excel olarak indir</a>
          <a href="#" onclick="exportAs('png')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Görüntü olarak indir</a>
        </div>
      </div>
    </div>
  </div>

  <!-- İçerik -->
  <div class="bg-white border border-gray-200 rounded shadow-sm mx-4 mb-4">
    <div class="p-4 flex flex-col sm:flex-row sm:justify-between sm:items-center">
      <div>
        <h1 class="text-lg font-semibold text-gray-800"><?= $faturaTuruBaslik ?> Detayları</h1>
        <p class="text-secondary mt-1 text-sm">Fatura No: #<?= htmlspecialchars($faturaNo) ?></p>
        <p class="text-sm text-gray-500">
          <?= $firmaEtiketi ?>: <?= htmlspecialchars($fatura['firma_ad'] . ' ' . $fatura['firma_soyad']) ?>
        </p>
        <?php if (!empty($orderNote)): ?>
          <p class="text-sm text-gray-500 mt-2">
            <span class="font-semibold text-gray-700">Sipariş Notu:</span> 
            <?= nl2br(htmlspecialchars($orderNote)) ?>
          </p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Arama -->
    <div class="relative px-4 mb-4">
      <input 
        id="searchInput"
        type="search"
        placeholder="Ürün ara..."
        class="w-full h-9 pl-9 pr-3 rounded bg-gray-50 border border-gray-200 text-sm focus:outline-none focus:ring-1 focus:ring-primary"
      />
      <i class="ri-search-line absolute left-6 top-1/2 -translate-y-1/2 text-gray-400"></i>
    </div>

    <div class="overflow-x-auto px-4 pb-4">
      <table class="w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
                <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İskonto</th>
                <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">KDV</th>
                <th class="py-2 px-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Tutar</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            <?php 
            $genel_toplam = 0;
            $genel_iskonto = 0;
            $genel_kdv = 0;
            foreach ($detaylar as $d): 
                $genel_toplam += $d['toplam_fiyat'];
                $genel_iskonto += $d['indirim_tutari'];
                $genel_kdv += $d['kdv_tutari'];
            ?>
                <tr>
                    <td class="py-2 px-3 text-sm">
                        <?= htmlspecialchars($d['name']) ?>
                        <?php if (!empty($d['urun_notu'])): ?>
                            <div class="text-xs text-blue-600"><?= htmlspecialchars($d['urun_notu']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="py-2 px-3 text-sm text-right">
                        <?= number_format($d['miktar'], 3, ',', '.') ?> <?= $d['olcum_birimi'] ?>
                    </td>
                    <td class="py-2 px-3 text-sm text-right">
                        <?= number_format($d['birim_fiyat'], 2, ',', '.') ?> ₺
                    </td>
                    <td class="py-2 px-3 text-sm text-right">
                        <?= number_format($d['toplam_fiyat'], 2, ',', '.') ?> ₺
                    </td>
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
                    <td class="py-2 px-3 text-sm text-right">
                        %<?= number_format($d['kdv_orani'], 0) ?>
                        (<?= number_format($d['kdv_tutari'], 2, ',', '.') ?> ₺)
                    </td>
                    <td class="py-2 px-3 text-sm text-right font-medium">
                        <?= number_format($d['net_tutar'], 2, ',', '.') ?> ₺
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr class="bg-gray-50 font-medium">
                <td colspan="3" class="py-2 px-3 text-sm text-right">Ara Toplam:</td>
                <td class="py-2 px-3 text-sm text-right"><?= number_format($genel_toplam, 2, ',', '.') ?> ₺</td>
                <td class="py-2 px-3 text-sm text-right text-red-600">
                    <?php if ($genel_iskonto > 0): ?>
                        -<?= number_format($genel_iskonto, 2, ',', '.') ?> ₺
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
                <td class="py-2 px-3 text-sm text-right">
                    <?= number_format($genel_kdv, 2, ',', '.') ?> ₺
                </td>
                <td class="py-2 px-3 text-sm text-right">
                    <?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺
                </td>
            </tr>
        </tfoot>
    </table>
    </div>

    <div class="flex justify-end px-4 pb-4">
      <div class="w-full sm:w-1/2 md:w-1/3 lg:w-1/4 border border-gray-200 rounded p-3 text-sm">
        <div class="flex justify-between py-1">
          <span class="text-gray-600">Ara Toplam:</span>
          <span class="font-medium"><?= number_format($genel_toplam, 2, ',', '.') ?> ₺</span>
        </div>
        <div class="flex justify-between py-1">
          <span class="text-gray-600">İskonto:</span>
          <span class="font-medium text-red-600">-<?= number_format($genel_iskonto, 2, ',', '.') ?> ₺</span>
        </div>
        <div class="flex justify-between py-1">
          <span class="text-gray-600">KDV Tutarı:</span>
          <span class="font-medium"><?= number_format($genel_kdv, 2, ',', '.') ?> ₺</span>
        </div>
        <div class="flex justify-between py-1 border-t border-gray-200 mt-2 pt-2">
          <span class="text-gray-800 font-medium">Genel Toplam:</span>
          <span class="text-primary font-semibold"><?= number_format($fatura['genel_toplam'], 2, ',', '.') ?> ₺</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Silme Onay Modalı -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg p-6 w-full max-w-md">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Faturayı Sil</h3>
    <p class="text-gray-500 mb-6">Bu faturayı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
    <div class="flex justify-end space-x-3">
      <button 
        type="button" 
        onclick="closeDeleteModal()"
        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-button text-sm"
      >
        İptal
      </button>
      <button 
        type="button" 
        onclick="deleteFatura()"
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

  function formatCurrency(a) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(a);
  }

  function renderTable(data) {
    const tbody = document.getElementById('tableBody');
    tbody.innerHTML = '';
    let subtotal = 0, taxAmount = 0;
    data.forEach(item => {
      const total = item.quantity * item.unitPrice;
      const taxVal = total * (item.tax / 100);
      subtotal += total;
      taxAmount += taxVal;

      const tr = document.createElement('tr');
      tr.className = 'border-b border-gray-200 hover:bg-gray-50';

      tr.innerHTML = `
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700">${item.code}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700">${item.name}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700 text-right">${item.quantity}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700 text-right">${formatCurrency(item.unitPrice)}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-700 text-right">%${item.tax}</td>
        <td class="px-3 py-2 text-xs sm:text-sm text-gray-800 text-right font-medium">
          ${formatCurrency(total + taxVal)}
        </td>
      `;
      tbody.appendChild(tr);
    });
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
    document.getElementById('taxAmount').textContent = formatCurrency(taxAmount);
    document.getElementById('total').textContent = formatCurrency(subtotal + taxAmount);
  }

  function showNotification(msg) {
    const n = document.getElementById('notification');
    document.getElementById('notificationText').textContent = msg;
    n.classList.remove('translate-x-full');
    setTimeout(() => n.classList.add('translate-x-full'), 3000);
  }

  function exportAs(fmt) {
    const fmts = { pdf: 'PDF', xlsx: 'Excel', png: 'Görüntü' };
    showNotification(`Fatura ${fmts[fmt]} olarak dışa aktarıldı (demo).`);
  }

  // Arama
  document.getElementById('searchInput').addEventListener('input', (e) => {
    const val = e.target.value.toLowerCase();
    const filtered = items.filter(x =>
      x.name.toLowerCase().includes(val) ||
      x.code.toLowerCase().includes(val)
    );
    renderTable(filtered);
  });

  // Tablo ilk gösterim
  renderTable(items);

  // Silme işlemleri için JavaScript
  let faturaIdToDelete = 0;
  
  function showDeleteModal(id) {
    faturaIdToDelete = id;
    document.getElementById('deleteModal').classList.remove('hidden');
  }
  
  function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
  }
  
  function deleteFatura() {
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
        window.location.href = document.referrer;
      } else {
        // Hata durumunda alert göster
        alert('Hata: ' + data.message);
        closeDeleteModal();
      }
    })
    .catch(error => {
      console.error('Error:', error);
      alert('İşlem sırasında bir hata oluştu.');
      closeDeleteModal();
    });
  }
  
  // Modal dışına tıklandığında kapatma
  document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closeDeleteModal();
    }
  });
  
  // ESC tuşu ile modalı kapatma
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeDeleteModal();
    }
  });
  
  // Dışa aktarma menüsü
  const exportBtn = document.getElementById('exportBtn');
  const exportMenu = document.getElementById('exportMenu');
  
  exportBtn.addEventListener('click', function() {
    exportMenu.classList.toggle('hidden');
  });
  
  // Dışa aktarma menüsü dışına tıklandığında kapatma
  document.addEventListener('click', function(e) {
    if (!exportBtn.contains(e.target) && !exportMenu.contains(e.target)) {
      exportMenu.classList.add('hidden');
    }
  });
</script>

<?php include 'includes/footer.php'; ?>



<?php
// urunler.php
require_once 'includes/db.php';   // Veritabanı bağlantısı
include 'includes/header.php';    // Header (sidebar + üst menü)

// Stok formatını düzenlemek için yardımcı fonksiyon
function formatStockDisplay($stok_miktari, $olcum_birimi, $stok_kg = null) {
    // Değerleri sayısal formata çevir
    $value = floatval($stok_miktari);
    
    // Sıfır kontrolü
    if ($value == 0) {
        if ($olcum_birimi === 'kg' || $olcum_birimi === 'gr') {
            return "0 kg (0 gr)";
        } else if ($olcum_birimi === 'adet') {
            return "0 adet";
        } else {
            return "0 {$olcum_birimi}";
        }
    }
    
    // Normal gösterim
    if ($olcum_birimi === 'kg') {
        // Kilogram ise hem kg hem gr olarak göster
        return number_format($value, 2, ',', '.') . " kg (" . number_format(round($value * 1000), 0, ',', '.') . " gr)";
    } else if ($olcum_birimi === 'gr') {
        // Gram değerlerini doğrudan stok_miktari alanından göster
        return number_format($value, 0, ',', '.') . " gr";
    } else if ($olcum_birimi === 'adet') {
        // Adet ise sadece sayı göster
        return number_format($value, 0, ',', '.') . " adet";
    } else {
        // Diğer birimler
        return number_format($value, 2, ',', '.') . " {$olcum_birimi}";
    }
}

// stok_kg alanını kontrol et - yoksa eklemeyi öner
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM urunler LIKE 'stok_kg'");
    $hasStokKgColumn = $checkColumn->rowCount() > 0;
    
    if (!$hasStokKgColumn) {
        echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-4">
            <div class="font-bold">Uyarı!</div>
            <p>Veritabanında "stok_kg" alanı bulunamadı. Bu alan, kg cinsinden ürünlerin stok görüntülemesi için gereklidir.</p>
            <div class="mt-2">
                <a href="stok_kg_ekle.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-1 px-3 rounded text-sm">
                    Alanı Ekle
                </a>
            </div>
        </div>';
    }
} catch(Exception $e) {
    // Sorgu hatasını yok say
}

// Ürünleri veritabanından çek
try {
    $stmt = $pdo->query("
        SELECT u.id, u.urun_adi, u.urun_kodu, u.barkod, u.stok_miktari, u.stok_kg, u.olcum_birimi, 
               u.birim_fiyat, u.resim_url, u.raf_no, u.ambalaj, u.koli_adeti, m.marka_adi
        FROM urunler u 
        LEFT JOIN markalar m ON u.marka_id = m.id 
        ORDER BY u.id DESC
    ");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Hata: " . $e->getMessage();
    $urunler = [];
}

// Statik markalar (geçici)
$markalar = [
    ['id' => 1, 'marka_adi' => 'Marka 1'],
    ['id' => 2, 'marka_adi' => 'Marka 2'],
    ['id' => 3, 'marka_adi' => 'Marka 3']
];

// Statik ambalaj tipleri (geçici)
$ambalajlar = [
    ['id' => 1, 'ambalaj_adi' => 'Kutu'],
    ['id' => 2, 'ambalaj_adi' => 'Paket'],
    ['id' => 3, 'ambalaj_adi' => 'Poşet']
];

$urunlerJson = json_encode($urunler, JSON_NUMERIC_CHECK);
?>

<style>
body {
  background-color: white;
}

/* Arama sonucu vurgulama stili */
mark {
  background-color: #fff3cd;
  color: #856404;
  padding: 0.1em 0.2em;
  border-radius: 2px;
  font-weight: 500;
}
</style>

<!-- Yeni Tasarım -->
<div class="p-0">
  <div class="max-w-full mx-auto">
    <div class="bg-white shadow-sm p-4 md:p-6">
      <!-- Arama ve Kontroller -->
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 md:gap-6">
        <!-- Arama Kutusu (Hem Mobil Hem Desktop için) -->
        <div class="relative flex-1 max-w-full md:max-w-md">
          <input 
            type="text" 
            id="searchInput"
            placeholder="Ürün ara... (Türkçe karakter destekli)" 
            class="w-full pl-10 pr-4 py-2.5 text-sm text-gray-700 border border-gray-200 rounded-lg focus:outline-none focus:border-primary"
          >
          <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>

        <!-- Mobil için Filtre ve Sıralama -->
        <div class="flex gap-2 md:hidden">
          <button 
            id="filterBtnMobile"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50"
          >
            <i class="ri-filter-3-line"></i>
            Filtrele
          </button>
          <button 
            id="sortBtnMobile"
            class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50"
          >
            <i class="ri-sort-desc"></i>
            Sırala
          </button>
        </div>
        
        <!-- Desktop için Kontroller -->
        <div class="hidden md:flex items-center gap-3 flex-wrap">
          <!-- Görünüm Seçenekleri -->
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 whitespace-nowrap">Görünüm:</span>
            <div class="flex border border-gray-200 rounded-lg p-1">
              <button 
                class="view-btn p-1.5 rounded hover:bg-gray-100" 
                data-view="grid"
                title="Grid Görünüm"
              >
                <i class="ri-grid-fill text-gray-700"></i>
              </button>
              <button 
                class="view-btn p-1.5 rounded hover:bg-gray-100" 
                data-view="list"
                title="Liste Görünüm"
              >
                <i class="ri-list-unordered text-gray-700"></i>
              </button>
              <button 
                class="view-btn p-1.5 rounded hover:bg-gray-100" 
                data-view="table"
                title="Tablo Görünüm"
              >
                <i class="ri-table-line text-gray-700"></i>
              </button>
            </div>
          </div>
          
          <!-- Göster Seçeneği -->
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 whitespace-nowrap">Göster:</span>
            <select id="showCount" class="pr-8 py-1.5 text-sm text-gray-700 border border-gray-200 rounded-lg focus:outline-none focus:border-primary">
              <option value="20">20</option>
              <option value="50" selected>50</option>
              <option value="100">100</option>
            </select>
          </div>
          
          <!-- Filtre Butonu -->
          <button 
            id="filterBtn"
            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50"
          >
            <i class="ri-filter-3-line"></i>
            Filtrele
          </button>
          
          <!-- Sıralama Butonu -->
          <button 
            id="sortBtn"
            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 border border-gray-200 rounded-lg hover:bg-gray-50"
          >
            <i class="ri-sort-desc"></i>
            Sırala
          </button>
          
          <!-- Ürün Ekle Butonu -->
          <a 
            href="urun_ekle.php"
            class="flex items-center gap-2 px-4 py-2 text-sm text-white bg-primary rounded-lg hover:bg-primary/90"
          >
            <i class="ri-add-line"></i>
            <span class="whitespace-nowrap">Ürün Ekle</span>
          </a>
        </div>

        <!-- Mobil için Ürün Ekle Butonu -->
        <div class="flex md:hidden">
          <a 
            href="urun_ekle.php"
            class="w-full flex items-center justify-center gap-2 px-4 py-2.5 text-sm text-white bg-primary rounded-lg hover:bg-primary/90"
          >
            <i class="ri-add-line"></i>
            Ürün Ekle
          </a>
        </div>
      </div>
      
      <!-- Ürünler Grid Görünümü -->
      <div id="productsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 mt-4">
        <?php foreach($urunler as $urun): ?>
          <div 
            class="product-item bg-white border border-gray-200 rounded-lg p-4 hover:border-primary transition-colors cursor-pointer"
            onclick="showProductDetail(<?= $urun['id'] ?>)"
          >
            <div class="aspect-square bg-gray-100 rounded-lg mb-3 overflow-hidden">
              <img 
                src="<?= $urun['resim_url'] ?? 'resimyok.jpg' ?>" 
                alt="<?= htmlspecialchars($urun['urun_adi']) ?>" 
                class="w-full h-full object-contain"
              >
            </div>
            <div>
              <h3 class="font-medium text-gray-900 truncate"><?= htmlspecialchars($urun['urun_adi']) ?></h3>
              <div class="mt-1 text-sm text-gray-500 flex items-center">
                <i class="ri-stack-line mr-1"></i>
                <span>Stok: <?= formatStockDisplay($urun['stok_miktari'], $urun['olcum_birimi'], $urun['stok_kg']) ?></span>
              </div>
            </div>
            <div class="mt-4 flex items-center justify-between">
              <span class="text-lg font-medium text-gray-900">
                <?= number_format($urun['birim_fiyat'], 2, ',', '.') ?> ₺
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Ürünler Liste Görünümü -->
      <div id="productsList" class="hidden space-y-3 mt-4">
        <?php foreach($urunler as $urun): ?>
          <div 
            class="product-item bg-white border border-gray-200 rounded-lg p-4 flex items-center gap-4 hover:border-primary transition-colors cursor-pointer"
            onclick="showProductDetail(<?= $urun['id'] ?>)"
          >
            <div class="w-20 h-20 bg-gray-100 rounded flex-shrink-0">
              <img 
                src="<?= $urun['resim_url'] ?? 'resimyok.jpg' ?>" 
                alt="<?= htmlspecialchars($urun['urun_adi']) ?>" 
                class="w-full h-full object-cover rounded"
              >
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="font-medium text-gray-900"><?= htmlspecialchars($urun['urun_adi']) ?></h3>
              <div class="mt-1 grid grid-cols-2 gap-2 text-sm text-gray-500">
                <span class="col-urun_kodu flex items-center">
                  <i class="ri-code-line w-4 h-4 mr-1"></i>
                  <?= htmlspecialchars($urun['urun_kodu']) ?>
                </span>
                <span class="col-barkod flex items-center">
                  <i class="ri-barcode-line w-4 h-4 mr-1"></i>
                  <?= htmlspecialchars($urun['barkod']) ?>
                </span>
                <span class="col-raf flex items-center">
                  <i class="ri-archive-line w-4 h-4 mr-1"></i>
                  Raf: <?= htmlspecialchars($urun['raf_no']) ?>
                </span>
                <span class="col-ambalaj flex items-center">
                  <i class="ri-box-3-line w-4 h-4 mr-1"></i>
                  <?= htmlspecialchars($urun['ambalaj']) ?>
                </span>
                <span class="col-stok flex items-center">
                  <i class="ri-stack-line w-4 h-4 mr-1"></i>
                  Stok: <?= formatStockDisplay($urun['stok_miktari'], $urun['olcum_birimi'], $urun['stok_kg']) ?>
                </span>
              </div>
            </div>
            <div class="flex-shrink-0 text-right">
              <div class="text-lg font-medium text-gray-900">
                <?= number_format($urun['birim_fiyat'], 2, ',', '.') ?> ₺
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Ürünler Tablo Görünümü -->
      <div id="productsTable" class="hidden mt-4">
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Ürün</th>
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Ürün Kodu</th>
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Barkod</th>
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Raf No</th>
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Ambalaj</th>
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Stok</th>
                <th class="py-4 px-4 text-left text-sm font-medium text-gray-500">Koli Adeti</th>
                <th class="py-4 px-4 text-right text-sm font-medium text-gray-500">Birim Fiyat</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach($urunler as $urun): ?>
                <tr class="product-item border-b border-gray-200 hover:bg-gray-50 cursor-pointer" onclick="showProductDetail(<?= $urun['id'] ?>)">
                  <td class="py-4 px-4">
                    <div class="flex items-center gap-3">
                      <img 
                        src="<?= $urun['resim_url'] ?? 'resimyok.jpg' ?>" 
                        alt="<?= htmlspecialchars($urun['urun_adi']) ?>" 
                        class="w-10 h-10 rounded object-cover"
                      >
                      <span class="text-sm text-gray-700"><?= htmlspecialchars($urun['urun_adi']) ?></span>
                    </div>
                  </td>
                  <td class="py-4 px-4 text-sm text-gray-700"><?= htmlspecialchars($urun['urun_kodu']) ?></td>
                  <td class="py-4 px-4 text-sm text-gray-700"><?= htmlspecialchars($urun['barkod']) ?></td>
                  <td class="py-4 px-4 text-sm text-gray-700"><?= htmlspecialchars($urun['raf_no']) ?></td>
                  <td class="py-4 px-4 text-sm text-gray-700"><?= htmlspecialchars($urun['ambalaj']) ?></td>
                  <td class="py-4 px-4 text-sm text-gray-700"><?= formatStockDisplay($urun['stok_miktari'], $urun['olcum_birimi'], $urun['stok_kg']) ?></td>
                  <td class="py-4 px-4 text-sm text-gray-700"><?= number_format($urun['koli_adeti'], 0, ',', '.') ?></td>
                  <td class="py-4 px-4 text-sm text-gray-700 text-right"><?= number_format($urun['birim_fiyat'], 2, ',', '.') ?> ₺</td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      
      <!-- Sayfalama -->
      <div class="flex items-center justify-between mt-6 pb-0">
        <div class="text-sm text-gray-500">
          Toplam <?= count($urunler) ?> ürün listeleniyor
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Filtre Modal -->
<div id="filterModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg w-full max-w-md mx-4">
    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-lg font-medium">Filtrele</h3>
      <button class="close-filter-modal text-gray-400 hover:text-gray-500">
        <i class="ri-close-line"></i>
      </button>
    </div>
    <div class="p-4">
      <form id="filterForm">
        <div class="mb-4">
          <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
          <select id="category" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            <option value="">Tümü</option>
            <option value="elektronik">Elektronik</option>
            <option value="giyim">Giyim</option>
            <option value="ev">Ev & Yaşam</option>
          </select>
        </div>
        <div class="mb-4">
          <label for="brand" class="block text-sm font-medium text-gray-700 mb-1">Marka</label>
          <select id="brand" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            <option value="">Tümü</option>
            <?php foreach($markalar as $marka): ?>
              <option value="<?= $marka['id'] ?>"><?= htmlspecialchars($marka['marka_adi']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Fiyat Aralığı</label>
          <div class="flex items-center gap-2">
            <input type="number" id="minPrice" placeholder="Min" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
            <span class="text-gray-500">-</span>
            <input type="number" id="maxPrice" placeholder="Max" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent">
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Stok Durumu</label>
          <div class="flex items-center gap-4">
            <div class="flex items-center">
              <input type="radio" id="stockAll" name="stock" value="all" checked class="h-4 w-4 text-primary focus:ring-primary border-gray-300">
              <label for="stockAll" class="ml-2 block text-sm text-gray-900">Tümü</label>
            </div>
            <div class="flex items-center">
              <input type="radio" id="stockAvailable" name="stock" value="available" class="h-4 w-4 text-primary focus:ring-primary border-gray-300">
              <label for="stockAvailable" class="ml-2 block text-sm text-gray-900">Stokta Var</label>
            </div>
            <div class="flex items-center">
              <input type="radio" id="stockLow" name="stock" value="low" class="h-4 w-4 text-primary focus:ring-primary border-gray-300">
              <label for="stockLow" class="ml-2 block text-sm text-gray-900">Stok Az</label>
            </div>
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" onclick="resetFilters()" class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
            Sıfırla
          </button>
          <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm hover:bg-primary/90">
            Uygula
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Sıralama Modal -->
<div id="sortModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg w-full max-w-md mx-4">
    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
      <h3 class="text-lg font-medium">Sırala</h3>
      <button class="close-sort-modal text-gray-400 hover:text-gray-500">
        <i class="ri-close-line"></i>
      </button>
    </div>
    <div class="p-4">
      <div class="space-y-2">
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="name-asc">
          <i class="ri-sort-asc text-gray-500 mr-3"></i>
          <span>İsme Göre (A-Z)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="name-desc">
          <i class="ri-sort-desc text-gray-500 mr-3"></i>
          <span>İsme Göre (Z-A)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="price-asc">
          <i class="ri-sort-asc text-gray-500 mr-3"></i>
          <span>Fiyata Göre (Artan)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="price-desc">
          <i class="ri-sort-desc text-gray-500 mr-3"></i>
          <span>Fiyata Göre (Azalan)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="stock-asc">
          <i class="ri-sort-asc text-gray-500 mr-3"></i>
          <span>Stok Miktarına Göre (Artan)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="stock-desc">
          <i class="ri-sort-desc text-gray-500 mr-3"></i>
          <span>Stok Miktarına Göre (Azalan)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="date-desc">
          <i class="ri-sort-desc text-gray-500 mr-3"></i>
          <span>Eklenme Tarihine Göre (Yeni-Eski)</span>
        </div>
        <div class="flex items-center p-2 rounded hover:bg-gray-50 cursor-pointer sort-option" data-sort="date-asc">
          <i class="ri-sort-asc text-gray-500 mr-3"></i>
          <span>Eklenme Tarihine Göre (Eski-Yeni)</span>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="assets/js/search.js"></script>
<script>
// Ürün detay sayfasına yönlendirme
function showProductDetail(id) {
  window.location.href = 'urun_detay.php?id=' + id;
}

// Türkçe karakter dönüşüm fonksiyonu
function convertTurkishToBasic(text) {
  const turkishChars = {
    'ı': 'i', 'İ': 'i',
    'ğ': 'g', 'Ğ': 'g',
    'ü': 'u', 'Ü': 'u',
    'ş': 's', 'Ş': 's',
    'ö': 'o', 'Ö': 'o',
    'ç': 'c', 'Ç': 'c',
    'â': 'a', 'Â': 'a',
    'î': 'i', 'Î': 'i',
    'û': 'u', 'Û': 'u'
  };
  
  return text.replace(/[ıİğĞüÜşŞöÖçÇâÂîÎûÛ]/g, letter => turkishChars[letter] || letter);
}

// Arama işlevselliği
const searchInput = document.getElementById('searchInput');

searchInput.addEventListener('input', function(e) {
  const searchTerm = e.target.value.toLowerCase().trim();
  const allProducts = document.querySelectorAll('.product-item');
  let visibleCount = 0;

  // Arama terimini boşluklara göre ayır ve Türkçe karakterleri dönüştür
  const searchTerms = searchTerm.split(' ')
    .filter(term => term.length > 0)
    .map(term => convertTurkishToBasic(term.toLowerCase()));

  allProducts.forEach(product => {
    try {
      // Grid görünümü için
      const productName = convertTurkishToBasic(product.querySelector('h3')?.textContent.toLowerCase() || '');
      
      // Liste ve tablo görünümü için ek alanlar
      const productCode = convertTurkishToBasic(product.querySelector('[class*="col-urun_kodu"]')?.textContent.toLowerCase() || 
                         product.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '');
      const productBarcode = convertTurkishToBasic(product.querySelector('[class*="col-barkod"]')?.textContent.toLowerCase() || 
                            product.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '');

      // Tüm arama terimleri için kontrol
      const isVisible = searchTerms.every(term => {
        return productName.includes(term) || 
               productCode.includes(term) || 
               productBarcode.includes(term);
      });

      if (isVisible) {
        product.style.display = '';
        visibleCount++;
        
        // Eşleşen metni vurgula
        highlightMatch(product, searchTerms);
      } else {
        product.style.display = 'none';
        
        // Vurgulamaları kaldır
        removeHighlights(product);
      }
    } catch (error) {
      console.error('Ürün arama hatası:', error);
    }
  });

  updateTotalCount(visibleCount);
});

// Eşleşen metni vurgulama fonksiyonu
function highlightMatch(product, searchTerms) {
  if (!product || searchTerms.length === 0) return;

  try {
    const elements = [
      product.querySelector('h3'),
      product.querySelector('[class*="col-urun_kodu"]'),
      product.querySelector('[class*="col-barkod"]'),
      ...Array.from(product.querySelectorAll('td'))
    ].filter(el => el); // null elemanları filtrele

    elements.forEach(element => {
      if (!element) return;
      
      let html = element.textContent;
      
      // Önce eski vurgulamaları kaldır
      html = html.replace(/<\/?mark>/g, '');
      
      // Yeni vurgulamaları ekle
      searchTerms.forEach(term => {
        if (term.length > 0) {
          const regex = new RegExp(`(${term})`, 'gi');
          html = html.replace(regex, '<mark>$1</mark>');
        }
      });
      
      element.innerHTML = html;
    });
  } catch (error) {
    console.error('Vurgulama hatası:', error);
  }
}

// Vurgulamaları kaldırma fonksiyonu
function removeHighlights(product) {
  if (!product) return;

  try {
    const elements = [
      product.querySelector('h3'),
      product.querySelector('[class*="col-urun_kodu"]'),
      product.querySelector('[class*="col-barkod"]'),
      ...Array.from(product.querySelectorAll('td'))
    ].filter(el => el);

    elements.forEach(element => {
      if (element) {
        element.innerHTML = element.innerHTML.replace(/<\/?mark>/g, '');
      }
    });
  } catch (error) {
    console.error('Vurgulama kaldırma hatası:', error);
  }
}

// Toplam ürün sayısını güncelle
function updateTotalCount(count) {
  const countElement = document.querySelector('.text-gray-500');
  if (countElement) {
    const countText = count !== undefined ? count : document.querySelectorAll('.product-item:not([style*="display: none"])').length;
    countElement.textContent = `Toplam ${countText} ürün listeleniyor`;
  }
}

// Görünüm değiştirme
document.addEventListener('DOMContentLoaded', function() {
  const viewBtns = document.querySelectorAll('.view-btn');
  const productsGrid = document.getElementById('productsGrid');
  const productsList = document.getElementById('productsList');
  const productsTable = document.getElementById('productsTable');
  
  // Görünüm butonlarına tıklama olayı ekle
  viewBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      // Aktif butonu güncelle
      viewBtns.forEach(b => b.classList.remove('bg-gray-100'));
      this.classList.add('bg-gray-100');
      
      // Görünümü değiştir
      const view = this.dataset.view;
      if (productsGrid) productsGrid.classList.add('hidden');
      if (productsList) productsList.classList.add('hidden');
      if (productsTable) productsTable.classList.add('hidden');
      
      switch(view) {
        case 'grid':
          if (productsGrid) productsGrid.classList.remove('hidden');
          break;
        case 'list':
          if (productsList) productsList.classList.remove('hidden');
          break;
        case 'table':
          if (productsTable) productsTable.classList.remove('hidden');
          break;
      }
      
      // Görünüm tercihini localStorage'a kaydet
      localStorage.setItem('productsView', view);
    });
  });
  
  // Sayfa yüklendiğinde son seçili görünümü göster
  const lastView = localStorage.getItem('productsView');
  if (lastView) {
    const btn = document.querySelector(`[data-view="${lastView}"]`);
    if (btn) {
      btn.click();
    } else if (document.querySelector('[data-view="grid"]')) {
      document.querySelector('[data-view="grid"]').click();
    }
  } else if (document.querySelector('[data-view="grid"]')) {
    document.querySelector('[data-view="grid"]').click();
  }
  
  // Modal işlemleri için gerekli elementler
  const filterBtn = document.getElementById('filterBtn');
  const filterBtnMobile = document.getElementById('filterBtnMobile');
  const filterModal = document.getElementById('filterModal');
  const closeFilterModalBtn = document.querySelector('.close-filter-modal');
  
  const sortBtn = document.getElementById('sortBtn');
  const sortBtnMobile = document.getElementById('sortBtnMobile');
  const sortModal = document.getElementById('sortModal');
  const closeSortModalBtn = document.querySelector('.close-sort-modal');

  // Modal işlevleri
  function handleFilterModal(action) {
    if (filterModal) {
      if (action === 'open') {
        filterModal.classList.remove('hidden');
        filterModal.classList.add('flex');
      } else {
        filterModal.classList.add('hidden');
        filterModal.classList.remove('flex');
      }
    }
  }

  function handleSortModal(action) {
    if (sortModal) {
      if (action === 'open') {
        sortModal.classList.remove('hidden');
        sortModal.classList.add('flex');
      } else {
        sortModal.classList.add('hidden');
        sortModal.classList.remove('flex');
      }
    }
  }

  // Modal event listeners
  if (filterBtn) filterBtn.addEventListener('click', () => handleFilterModal('open'));
  if (filterBtnMobile) filterBtnMobile.addEventListener('click', () => handleFilterModal('open'));
  if (closeFilterModalBtn) closeFilterModalBtn.addEventListener('click', () => handleFilterModal('close'));

  if (sortBtn) sortBtn.addEventListener('click', () => handleSortModal('open'));
  if (sortBtnMobile) sortBtnMobile.addEventListener('click', () => handleSortModal('open'));
  if (closeSortModalBtn) closeSortModalBtn.addEventListener('click', () => handleSortModal('close'));

  // Sıralama seçeneklerine tıklama olayı ekle
  const sortOptions = document.querySelectorAll('.sort-option');
  sortOptions.forEach(option => {
    option.addEventListener('click', function() {
      const sortValue = this.dataset.sort;
      console.log('Sıralama:', sortValue);
      
      // Burada sıralama işlemi yapılabilir
      // ...
      
      // Modalı kapat
      handleSortModal('close');
    });
  });

  // Modalların dışına tıklandığında kapatma
  window.addEventListener('click', function(e) {
    if (filterModal && e.target === filterModal) {
      handleFilterModal('close');
    }
    
    if (sortModal && e.target === sortModal) {
      handleSortModal('close');
    }
  });
});

// Filtre fonksiyonları
function resetFilters() {
  const elements = {
    category: document.getElementById('category'),
    brand: document.getElementById('brand'),
    minPrice: document.getElementById('minPrice'),
    maxPrice: document.getElementById('maxPrice'),
    stockAll: document.getElementById('stockAll')
  };

  if (elements.category) elements.category.value = '';
  if (elements.brand) elements.brand.value = '';
  if (elements.minPrice) elements.minPrice.value = '';
  if (elements.maxPrice) elements.maxPrice.value = '';
  if (elements.stockAll) elements.stockAll.checked = true;
}
</script>

<?php include 'includes/footer.php'; ?>

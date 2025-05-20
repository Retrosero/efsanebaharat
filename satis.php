<?php
// satis.php
require_once 'includes/db.php';
include 'includes/header.php'; // Soldaki menü + top bar layout

// musteriler tablosu (müşteri seçimi)
$customerRows = [];
try {
  // Müşterileri alfabetik sıraya göre getir (ad ve soyad'a göre)
  $stmtC = $pdo->query("SELECT id, ad, soyad, cari_bakiye, usd_bakiye, eur_bakiye, gbp_bakiye FROM musteriler ORDER BY ad ASC, soyad ASC");
  $customerRows = $stmtC->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  // ignore
}

// Ürünleri veritabanından çek
$urunler = [];
try {
    $stmt = $pdo->query("
        SELECT 
            id,
            urun_adi,
            urun_kodu,
            barkod,
            birim_fiyat as satis_fiyati,
            stok_miktari,
            olcum_birimi,
            resim_url,
            resim_url_2,
            resim_url_3,
            resim_url_4,
            resim_url_5,
            resim_url_6,
            resim_url_7,
            resim_url_8,
            resim_url_9,
            resim_url_10
        FROM urunler 
        ORDER BY urun_adi
    ");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}

// Ürünleri JSON formatına çevir
$urunlerJson = json_encode($urunler, JSON_NUMERIC_CHECK);
?>
<div class="p-2">
  <h1 class="text-lg sm:text-xl font-semibold mb-3"></h1>

  <!-- Yeni Üst Menü Tasarımı - Sabit (Fixed) -->
  <div class="flex items-center justify-between gap-2 mb-3 sticky top-0 z-40 bg-white p-2 border-b shadow-sm">
    <!-- Arama Kutusu -->
    <div class="relative flex-1">
      <input
        type="text"
        id="searchInput"
        placeholder="Ürün ara... (Türkçe karakter destekli)"
        class="w-full pl-3 pr-10 py-2 rounded-lg border border-gray-200 focus:outline-none focus:border-primary text-xs sm:text-sm"
      >
      <button class="absolute right-0 top-0 h-full px-2 text-gray-400">
        <i class="ri-search-line ri-lg"></i>
      </button>
    </div>
    
    <!-- Filtre ve Sıralama Butonları -->
    <div class="flex items-center gap-2">
      <!-- Filtre Butonu -->
      <button
        onclick="toggleFilter()"
        class="flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg"
      >
        <i class="ri-filter-3-line"></i>
        <span class="hidden sm:inline">Filtre</span>
      </button>

      <!-- Sıralama Butonu -->
      <button
        onclick="toggleSort()"
        class="flex items-center gap-1 px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg"
      >
        <i class="ri-sort-desc"></i>
        <span class="hidden sm:inline">Sıralama</span>
      </button>
    </div>
    
    <!-- Sepet Butonu -->
    <button
      id="cartBtn"
      class="relative p-2 text-gray-600 hover:bg-gray-100 rounded-full"
      onclick="toggleCart()"
    >
      <i class="ri-shopping-cart-2-line text-xl"></i>
      <span
        class="absolute -top-1 -right-1 w-5 h-5 flex items-center justify-center bg-primary text-white text-xs rounded-full"
        id="cartCount"
      >0</span>
    </button>
  </div>
  
  <!-- Ürün Grid Alanı -->
  <div id="productGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-3 lg:gap-4">
    <!-- JavaScript ile doldurulacak -->
  </div>
</div>

<!-- Müşteri Seçimi Modal -->
<div
  id="customerModal"
  class="modal fixed inset-0 bg-black bg-opacity-50 z-60 hidden"
>
  <div
    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg bg-white rounded-lg shadow-xl"
  >
    <div class="p-4">
      <h3 class="text-base sm:text-lg font-medium mb-3">Müşteri Seçimi</h3>
      <div class="relative mb-3">
        <input
          type="text"
          id="customerSearch"
          placeholder="Müşteri ara..."
          class="w-full pl-3 pr-10 py-2 rounded border border-gray-200 focus:outline-none focus:border-primary text-xs sm:text-sm"
        >
        <button class="absolute right-0 top-0 h-full px-3 text-gray-400">
          <i class="ri-search-line"></i>
        </button>
      </div>
      <div class="max-h-80 overflow-y-auto">
        <div class="space-y-1" id="customerList">
          <!-- Müşteriler JS ile listelenecek -->
        </div>
      </div>
      <div class="flex justify-end mt-3">
        <button 
          onclick="toggleCustomerModal()" 
          class="px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-button"
        >
          Kapat
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Filter Modal (Başlangıçta gizli) -->
<div
  id="filterModal"
  class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
>
  <div
    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-lg bg-white rounded-lg shadow-xl p-4"
  >
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-base sm:text-lg font-medium">Filtreler</h3>
      <button onclick="toggleFilter()" class="p-1.5 hover:bg-gray-100 rounded-full">
        <i class="ri-close-line ri-lg"></i>
      </button>
    </div>
    <div class="space-y-3">
      <div>
        <label class="block text-xs sm:text-sm font-medium mb-1">Fiyat Aralığı</label>
        <div class="flex gap-2">
          <input
            type="number"
            id="minPrice"
            placeholder="Min"
            class="flex-1 px-2 py-1.5 border rounded text-xs sm:text-sm"
          >
          <input
            type="number"
            id="maxPrice"
            placeholder="Max"
            class="flex-1 px-2 py-1.5 border rounded text-xs sm:text-sm"
          >
        </div>
      </div>
      <div>
        <label class="block text-xs sm:text-sm font-medium mb-1">Stok Durumu</label>
        <div class="flex items-center gap-2">
          <input
            type="checkbox"
            id="showOutOfStock"
            class="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
          >
          <label for="showOutOfStock" class="text-sm text-gray-700">Stokta Olmayanları Göster</label>
        </div>
      </div>
    </div>
    <div class="mt-4 flex justify-end gap-2">
      <button
        onclick="resetFilters()"
        class="px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-button"
      >
        Sıfırla
      </button>
      <button
        onclick="applyFilter()"
        class="px-3 py-1.5 bg-primary text-white text-xs sm:text-sm rounded-button"
      >
        Uygula
      </button>
    </div>
  </div>
</div>

<!-- Sort Modal (Başlangıçta gizli) -->
<div
  id="sortModal"
  class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
>
  <div
    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[95%] sm:w-full max-w-sm bg-white rounded-lg shadow-xl p-3"
  >
    <div class="flex items-center justify-between mb-3">
      <h3 class="text-base sm:text-lg font-medium">Sıralama</h3>
      <button onclick="toggleSort()" class="p-1.5 hover:bg-gray-100 rounded-full">
        <i class="ri-close-line ri-lg"></i>
      </button>
    </div>
    <div class="space-y-1">
      <button onclick="sortProducts('name')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        <i class="ri-sort-asc mr-2"></i>İsme göre (A-Z)
      </button>
      <button onclick="sortProducts('name-desc')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        <i class="ri-sort-desc mr-2"></i>İsme göre (Z-A)
      </button>
      <button onclick="sortProducts('price')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        <i class="ri-sort-asc mr-2"></i>Fiyat (Düşük → Yüksek)
      </button>
      <button onclick="sortProducts('price-desc')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        <i class="ri-sort-desc mr-2"></i>Fiyat (Yüksek → Düşük)
      </button>
      <button onclick="sortProducts('stock')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        <i class="ri-sort-asc mr-2"></i>Stok (Az → Çok)
      </button>
      <button onclick="sortProducts('stock-desc')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        <i class="ri-sort-desc mr-2"></i>Stok (Çok → Az)
      </button>
    </div>
  </div>
</div>

<!-- Cart Sidebar -->
<div
  id="cartSidebar"
  class="cart-sidebar fixed top-0 right-0 w-full sm:w-96 h-full bg-white shadow-xl z-55 hidden"
>
  <div class="flex flex-col h-full">
    <div class="p-4 border-b">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium">Sepetim</h3>
        <div class="flex items-center gap-2">
          <button id="closeCart" class="p-2 hover:bg-gray-100 rounded-full">
            <i class="ri-close-line ri-lg"></i>
          </button>
        </div>
      </div>
    </div>
    <div class="flex-1 overflow-y-auto p-4">
      <div id="cartItems" class="space-y-4">
        <!-- Cart items JS ile -->
      </div>
    </div>
    
      
      <!-- Net Tutar alanı ve Sepet işlem butonları daha düzenli şekilde yerleştirildi -->
      <div class="space-y-4 border-t pt-4 mt-3">
        <!-- Net Tutar Toggle Butonu (dropdown'a tıklanabilir) -->
        <div id="netTutarToggle" class="flex items-center justify-between cursor-pointer bg-gray-50 p-3 rounded-lg">
          <span class="font-semibold text-gray-800">Genel Toplam:</span>
          <div class="flex items-center">
            <span id="totalSummary" class="font-bold text-primary text-lg">0,00 ₺</span>
            <i class="ri-arrow-down-s-line ml-2 transform transition-transform duration-200" id="netTutarArrow"></i>
          </div>
        </div>
        
        <!-- Detaylı bilgiler - başlangıçta gizli -->
        <div id="cartDetailSection" class="space-y-3 mt-3 hidden border-t pt-3 bg-white p-3 rounded-lg shadow-sm">
          <!-- Müşteri Seçimi Alanı -->
          <div id="cartCustomerSection">
            <div class="text-sm font-medium mb-2">Cari Seçimi</div>
            <div id="customerSelectionArea">
              <!-- Müşteri seçilmemiş - arama alanı gözükecek -->
              <div id="noCustomerSelected" class="w-full">
                <div class="relative">
                  <input
                    type="text"
                    id="customerSearchDropdown"
                    placeholder="Müşteri ara..."
                    class="w-full px-3 py-2 border rounded-lg text-sm"
                    autocomplete="off"
                  >
                  <i class="ri-search-line absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                  
                  <!-- Dropdown müşteri listesi -->
                  <div id="customerDropdown" class="absolute left-0 right-0 mt-1 max-h-60 overflow-y-auto bg-white border border-gray-200 rounded-lg shadow-lg z-30 hidden">
                    <div id="customerDropdownList" class="py-1">
                      <!-- Müşteriler JavaScript ile doldurulacak -->
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Müşteri seçilmiş - bilgileri gözükecek -->
              <div id="customerSelectedInfo" class="w-full hidden">
                <div class="flex items-center justify-between mb-1">
                  <div class="text-sm font-medium" id="selectedCustomerNameDetail">Müşteri Seçilmedi</div>
                  <div class="flex items-center gap-2">
                    <button onclick="clearSelectedCustomer()" class="text-xs text-blue-500">Değiştir</button>
                  </div>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-xs text-gray-500">Bakiye:</span>
                  <span id="customerBalance" class="text-xs font-medium">0,00 ₺</span>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Toplam Bilgileri -->
          <div class="space-y-2 mt-3">
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600">Genel Toplam:</span>
              <span id="subtotal" class="text-sm font-medium text-gray-800">0,00 ₺</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-gray-600">İskonto Tutarı:</span>
              <span id="discountAmount" class="text-sm font-medium text-gray-800">0,00 ₺</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm font-semibold text-gray-800">Net Tutar:</span>
              <span id="total" class="text-sm font-semibold text-gray-800">0,00 ₺</span>
            </div>
          </div>
          
          <!-- İskonto ve Not Butonları -->
          <div class="flex gap-2 justify-end mt-3">
            <!-- İskonto Ekle -->
            <button
              onclick="toggleDiscountModal()"
              class="p-2 border border-gray-200 text-gray-600 text-sm rounded-full hover:bg-gray-50"
              title="İskonto Ekle"
            >
              <i class="ri-percent-line"></i>
            </button>
            
            <!-- Not Ekle -->
            <button
              onclick="showOrderNoteModal()"
              class="p-2 border border-gray-200 text-gray-600 text-sm rounded-full hover:bg-gray-50"
              title="Not Ekle"
            >
              <i class="ri-chat-1-line"></i>
            </button>
          </div>
        </div>
        
        <!-- Sepet İşlem Butonları -->
        <div class="flex gap-2 mt-2 mb-16 md:mb-2 z-40 bg-white">
          <!-- Sepeti Temizle -->
          <button 
            onclick="clearCart()"
            class="flex-1 py-3 bg-red-500 hover:bg-red-600 text-white text-sm rounded-md flex items-center justify-center gap-1"
          >
            <i class="ri-delete-bin-line"></i>
            <span>Sepeti Temizle</span>
          </button>
          
          <!-- Sipariş Tamamla -->
          <button 
            onclick="completeOrder()"
            class="flex-1 py-3 bg-primary hover:bg-primary-dark text-white text-sm rounded-md flex items-center justify-center gap-1" 
          >
            <i class="ri-check-line"></i>
            <span>Siparişi Tamamla</span>
          </button>
        </div>
      </div>
    </div>
  </div>
      </div>
      
<!-- Item Note Modal -->
      <div
  id="itemNoteModal"
  class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
      >
        <div
    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-lg shadow-xl"
        >
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium">Ürün Notu</h3>
        <button onclick="toggleItemNoteModal()" class="p-2 hover:bg-gray-100 rounded-full">
          <i class="ri-close-line ri-lg"></i>
              </button>
            </div>
      <textarea
        id="itemNote"
        class="w-full h-32 p-3 border rounded resize-none text-sm"
        placeholder="Ürün notunuzu buraya yazın..."
      ></textarea>
      <div class="mt-6 flex justify-end gap-4">
        <button
          onclick="toggleItemNoteModal()"
          class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-button"
              >
                İptal
              </button>
              <button
          onclick="saveItemNote()"
          class="px-4 py-2 bg-primary text-white text-sm rounded-button"
              >
          Kaydet
        </button>
      </div>
          </div>
        </div>
      </div>

<!-- Discount Modal -->
      <div
  id="discountModal"
  class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
      >
        <div
    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-lg shadow-xl"
        >
          <div class="p-4">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium">İskonto Ekle</h3>
        <button onclick="toggleDiscountModal()" class="p-2 hover:bg-gray-100 rounded-full">
          <i class="ri-close-line ri-lg"></i>
              </button>
            </div>
      <div class="mb-4">
        <label for="discountRate" class="block text-sm text-gray-600 mb-1">İskonto Oranı (%)</label>
        <input
          type="number"
          id="discountRate"
          class="w-full px-3 py-2 border rounded focus:outline-none focus:border-primary"
          placeholder="Örn: 10"
          min="0"
          max="100"
        >
      </div>
      <div class="flex justify-end">
              <button
          onclick="applyDiscount()"
          class="px-4 py-2 bg-primary text-white text-sm rounded-button"
              >
          Uygula
              </button>
      </div>
        </div>
  </div>
</div>

<!-- Order Note Modal -->
<div
  id="orderNoteModal"
  class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
>
  <div
    class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white rounded-lg shadow-xl"
  >
    <div class="p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium">Sipariş Notu</h3>
        <button onclick="toggleOrderNoteModal()" class="p-2 hover:bg-gray-100 rounded-full">
          <i class="ri-close-line ri-lg"></i>
        </button>
      </div>
      <textarea
        id="orderNote"
        class="w-full h-32 p-3 border rounded resize-none text-sm"
        placeholder="Sipariş notunuzu buraya yazın..."
      ></textarea>
      <div class="mt-6 flex justify-end gap-4">
        <button
          onclick="toggleOrderNoteModal()"
          class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-button"
        >
          İptal
        </button>
        <button
          onclick="saveOrderNote()"
          class="px-4 py-2 bg-primary text-white text-sm rounded-button"
        >
          Kaydet
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Image Viewer Modal -->
<div id="imageViewerModal" class="fixed inset-0 bg-black bg-opacity-90 hidden z-50">
    <div class="absolute inset-0 flex items-center justify-center">
        <!-- Kapat butonu -->
        <button onclick="closeImageViewer()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 z-10">
            <i class="ri-close-line"></i>
        </button>
        
        <!-- Önceki buton -->
        <button id="prevImage" class="absolute left-4 text-white text-4xl hover:text-gray-300 z-10">
            <i class="ri-arrow-left-line"></i>
        </button>
        
        <!-- Ana resim -->
        <div class="relative max-w-4xl max-h-[80vh]">
            <img id="viewerImage" src="" alt="" class="max-w-full max-h-[80vh] object-contain">
        </div>
        
        <!-- Sonraki buton -->
        <button id="nextImage" class="absolute right-4 text-white text-4xl hover:text-gray-300 z-10">
            <i class="ri-arrow-right-line"></i>
        </button>
        
        <!-- Küçük resimler -->
        <div class="absolute bottom-4 left-0 right-0 flex justify-center">
            <div id="thumbContainer" class="flex gap-2 px-4 overflow-x-auto max-w-full scrollbar-thin"></div>
        </div>
    </div>
</div>

<style>
/* Görünüm butonları için stil */
.view-btn {
    border-right: 1px solid #eee;
}

.view-btn:last-child {
    border-right: none;
}

.view-btn.active {
    background-color: #3176FF;
    color: white;
}

.view-btn.active:hover {
    background-color: #2861d1;
}

/* Responsive düzenlemeler */
@media (max-width: 640px) {
  .view-btn {
    padding: 8px;
  }
  
  .view-btn i {
    margin-right: 0;
  }
}

/* Ürün kartları için responsive grid */
.products-grid {
  display: grid;
  gap: 1rem;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}

@media (max-width: 640px) {
  .products-grid {
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
  }
}

/* Tablo görünümü için responsive düzenlemeler */
.products-table {
  width: 100%;
  overflow-x: auto;
}

.products-table table {
  min-width: 100%;
}

@media (max-width: 768px) {
  .products-table th,
  .products-table td {
    padding: 8px;
    font-size: 14px;
  }
  
  .products-table .mobile-hide {
    display: none;
  }
}

/* Liste görünümü için responsive düzenlemeler */
.products-list .product-item {
  padding: 12px;
}

@media (max-width: 640px) {
  .products-list .product-item {
    padding: 8px;
  }
  
  .products-list .product-details {
    flex-direction: column;
  }
  
  .products-list .product-info {
    width: 100%;
  }
}

/* Mevcut CSS içine bu bölümü ekleyin veya güncelleyin */
:where([class^="ri-"])::before { content: "\f3c2"; }
body { font-family: 'Inter', sans-serif; }

/* z-index özelleştirmeleri */
.cart-sidebar { z-index: 55 !important; }
#customerModal { z-index: 60 !important; }
#itemNoteModal, #orderNoteModal, #discountModal { z-index: 65 !important; }
#imageViewerModal { z-index: 70 !important; }
#netTutarToggle, #cartDetailSection { position: relative; z-index: 30 !important; }
#customerDropdown { z-index: 35 !important; }

/* Müşteri dropdown stilleri */
#customerDropdown {
  box-shadow: 0 4px 6px rgba(0,0,0,0.1);
  max-height: 200px;
  overflow-y: auto;
}

#customerDropdownList > div {
  transition: background-color 0.15s;
}

#customerDropdownList > div:hover {
  background-color: #f3f4f6;
}

/* Bottom Navigation için ayarlar */
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
  box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
  z-index: 50;
}

/* Mobil sepet düzenlemeleri */
@media (max-width: 640px) {
  .cart-sidebar {
    padding-bottom: 5rem; /* Bottom nav için ekstra padding */
  }
  #cartItems {
    padding-bottom: 0; /* Cart items için boşluk kaldırıldı */
  }
  .cart-submit-fixed {
    position: fixed;
    bottom: 60px; /* Bottom navigation'dan biraz yukarda */
    left: 0;
    right: 0;
    background-color: #fff;
    padding: 0.75rem;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    z-index: 56;
  }
  
  /* Sepet butonları için güncelleme */
  .flex.gap-2.mt-2.mb-16 {
    position: relative;
    padding-top: 0.5rem;
    padding-bottom: 0.5rem;
  }
  
  /* Mobil görünümde bottom nav için ek padding */
  @media (max-width: 768px) {
    .mb-16 {
      margin-bottom: 4rem;
    }
  }
}

/* Sol menü stil düzenlemeleri */
#sideNav {
  transition: all 0.3s ease;
  z-index: 100;
}
#sideNav.collapsed {
  width: 64px !important;
}
#sideNav.collapsed .nav-text,
#sideNav.collapsed .logo-text,
#sideNav.collapsed .menu-category {
  display: none;
}
#sideNav.collapsed .tooltip {
  justify-content: center;
}
#sideNav.collapsed .ri-menu-fold-line:before {
  content: "\f264"; /* menu-unfold ikonu */
}

/* Tooltip stil */
.tooltip {
  position: relative;
}
.tooltip:hover:after {
  content: attr(data-tooltip);
  position: absolute;
  left: 100%;
  top: 50%;
  transform: translateY(-50%);
  background: #1a1a1a;
  color: white;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 12px;
  white-space: nowrap;
  margin-left: 10px;
  z-index: 1000;
}
#sideNav:not(.collapsed) .tooltip:hover:after {
  display: none;
}

/* Mobil cihazlar için responsive navbar ayarları */
@media (max-width: 640px) {
  /* ...gerekli diğer stiller... */
}

/* Customize scrollbar */
.scrollbar-thin::-webkit-scrollbar {
    height: 6px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.scrollbar-thin::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>

<script>
// musteriler
const customersFromDB = <?php echo json_encode($customerRows); ?>;

// Ürünleri veritabanından çek
let products = <?php echo $urunlerJson; ?>;
let filteredProducts = []; // Filtrelenmiş ürünler için yeni dizi
let cart = []; // Will be populated from localStorage
let selectedCustomer = null;
let searchValue = '';
const currentView = 'grid'; // Her zaman grid görünümü kullanılacak

// Filtre değişkenleri
let showOutOfStock = false;
let minPrice = 0;
let maxPrice = Infinity;

// --- localStorage Functions ---
function saveCartToLocalStorage() {
    localStorage.setItem('shoppingCartSatis', JSON.stringify(cart));
}

function loadCartFromLocalStorage() {
    const storedCart = localStorage.getItem('shoppingCartSatis');
    if (storedCart) {
        cart = JSON.parse(storedCart);
    } else {
        cart = []; 
    }
}

function saveDiscountRateToLocalStorage() {
    const discountRateValue = document.getElementById('discountRate').value;
    localStorage.setItem('discountRateSatis', discountRateValue);
}

function loadDiscountRateFromLocalStorage() {
    const storedDiscountRate = localStorage.getItem('discountRateSatis');
    if (storedDiscountRate) {
        document.getElementById('discountRate').value = parseFloat(storedDiscountRate) || 0;
    }
}

function saveOrderNoteToLocalStorage() {
    const orderNoteValue = document.getElementById('orderNote').value;
    localStorage.setItem('orderNoteSatis', orderNoteValue);
}

function loadOrderNoteFromLocalStorage() {
    const storedOrderNote = localStorage.getItem('orderNoteSatis');
    if (storedOrderNote) {
        document.getElementById('orderNote').value = storedOrderNote;
    }
}

function saveFilterStateToLocalStorage() {
    localStorage.setItem('showOutOfStockSatis', showOutOfStock);
    localStorage.setItem('minPriceSatis', minPrice);
    localStorage.setItem('maxPriceSatis', maxPrice === Infinity ? '' : maxPrice);
}

function loadFilterStateFromLocalStorage() {
    // Stokta olmayanları göster
    const storedShowOutOfStock = localStorage.getItem('showOutOfStockSatis');
    if (storedShowOutOfStock !== null) {
        showOutOfStock = storedShowOutOfStock === 'true';
        document.getElementById('showOutOfStock').checked = showOutOfStock;
    }
    
    // Minimum fiyat
    const storedMinPrice = localStorage.getItem('minPriceSatis');
    if (storedMinPrice !== null) {
        minPrice = parseFloat(storedMinPrice) || 0;
        document.getElementById('minPrice').value = minPrice > 0 ? minPrice : '';
    }
    
    // Maksimum fiyat
    const storedMaxPrice = localStorage.getItem('maxPriceSatis');
    if (storedMaxPrice !== null && storedMaxPrice !== '') {
        maxPrice = parseFloat(storedMaxPrice) || Infinity;
        document.getElementById('maxPrice').value = maxPrice < Infinity ? maxPrice : '';
    }
}
// --- End localStorage Functions ---

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
  // Verilerini JavaScript'e aktar
  const productsFromDB = <?= $urunlerJson ?>;
  
  // Current cart state
  let cart = [];
  let selectedCustomer = null;
  let activeDiscount = { type: 'percentage', value: 0 };
  let orderNote = '';
  let currentView = 'grid';
  let activeProductSort = 'name';
  let sortDirection = 'asc';
  let activeProductFilter = {minPrice: 0, maxPrice: Infinity, showOutOfStock: true};
  
  // Filtered products
  let filteredProducts = [...productsFromDB];
  
    // Verileri yükle
    loadCartFromLocalStorage();
    loadDiscountRateFromLocalStorage();
    loadOrderNoteFromLocalStorage();
    loadFilterStateFromLocalStorage();

    // Ürün Filtreleme ve Görüntüleme
    initEvents();
    filterAndRenderProducts();
  renderProducts();
    updateCartCount();
    renderCart();
  
  // Kategori seçildiğinde olayını ekle
  document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.category-btn').forEach(b => b.classList.remove('active'));
      this.classList.add('active');
    });
  });

  // Eski müşteri arama kutusuna event listener ekle
  const customerSearch = document.getElementById('customerSearch');
  if (customerSearch) {
    customerSearch.addEventListener('input', filterCustomers);
  }
    
    // Müşteri Araması ve Listesi
    renderCustomerList();
  
  // Yeni müşteri arama dropdown için event listener
  const customerSearchDropdown = document.getElementById('customerSearchDropdown');
  if (customerSearchDropdown) {
        // Önceki event listener'ları kaldır
        customerSearchDropdown.removeEventListener('input', filterCustomersDropdownHandler);
        customerSearchDropdown.removeEventListener('focus', focusDropdownHandler);
        
        // Yeni event listener'ları ekle
        customerSearchDropdown.addEventListener('input', filterCustomersDropdownHandler);
        customerSearchDropdown.addEventListener('focus', focusDropdownHandler);
    }
  
    // Net Tutar toggle işlevi
  const netTutarToggle = document.getElementById('netTutarToggle');
  const netTutarArrow = document.getElementById('netTutarArrow');
  const cartDetailSection = document.getElementById('cartDetailSection');
  
  if (netTutarToggle) {
    netTutarToggle.addEventListener('click', () => {
      // Detay bölümünün görünürlüğünü değiştir
      cartDetailSection.classList.toggle('hidden');
      // İkonu döndür (aşağı ok -> yukarı ok)
      netTutarArrow.classList.toggle('rotate-180');
      
      // Eğer alan açıldıysa ve dropdown varsa, dropdown'ı kontrol et
      if (!cartDetailSection.classList.contains('hidden')) {
        const customerDropdown = document.getElementById('customerDropdown');
        if (customerDropdown && !customerDropdown.classList.contains('hidden')) {
          customerDropdown.classList.add('hidden');
        }
      }
    });
  }

  // Müşteri modalindeki kapat butonu
  const customerModalCloseBtn = document.querySelector('#customerModal button:last-child');
  if (customerModalCloseBtn) {
    customerModalCloseBtn.onclick = closeCustomerModal;
  }
    
    // Dropdown dışına tıklanınca kapanması için
    document.addEventListener('click', function(e) {
        const dropdown = document.getElementById('customerDropdown');
        if (!dropdown) return;
        
        const searchDropdown = document.getElementById('customerSearchDropdown');
        if (!dropdown.contains(e.target) && e.target !== searchDropdown) {
            dropdown.classList.add('hidden');
        }
    });
});

// Müşteri arama işleyicisi
function filterCustomersDropdownHandler(e) {
    const searchValue = e.target.value.trim();
    const dropdown = document.getElementById('customerDropdown');
    
    // Input değeri boş değilse dropdown'ı göster
    if (dropdown) {
        dropdown.classList.remove('hidden');
        // Arama sonuçlarını filtrele ve göster
        filterCustomersDropdown(searchValue);
    }
}

// Focus işleyicisi
function focusDropdownHandler() {
    const dropdown = document.getElementById('customerDropdown');
    if (dropdown) {
        dropdown.classList.remove('hidden');
        // İlk açılışta tüm müşterileri göster
        filterCustomersDropdown('');
    }
}

function initEvents(){
  // Arama
  const searchInput = document.getElementById('searchInput');
  
  // Anlık arama için input event listener
  if (searchInput) {
  searchInput.addEventListener('input', (e) => {
    searchValue = e.target.value.trim();
    filterAndRenderProducts(); // Use the main filtering and rendering logic
  });

  // Barkod okuyucu için enter tuşu desteği
  searchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      searchValue = e.target.value.trim();
      filterAndRenderProducts(); // Use the main filtering and rendering logic
      searchInput.select(); // Enter'a basınca tüm metni seç
    }
  });
  }
  
  // Sepet kapama
  const closeCartBtn = document.getElementById('closeCart');
  if (closeCartBtn) {
    closeCartBtn.addEventListener('click',()=>{
    const cs=document.getElementById('cartSidebar');
      if (cs) {
    cs.classList.add('hidden');
    cs.classList.remove('show');
      }
  });
  }
  
  // Dış click
  document.addEventListener('click',(e)=>{
    const cartSidebar=document.getElementById('cartSidebar');
    const cartBtn=document.getElementById('cartBtn');
    if (cartSidebar && !cartSidebar.classList.contains('hidden') && cartBtn) { // Check if sidebar and button exist and sidebar is visible
      if(e.target && cartSidebar && cartBtn && !cartSidebar.contains(e.target) && !cartBtn.contains(e.target)){
        cartSidebar.classList.remove('show');
        cartSidebar.classList.add('hidden'); // Ensure it's hidden
      }
    }
  });
  
  // Müşteri Arama
  const customerSearch = document.getElementById('customerSearch');
  if (customerSearch) {
    customerSearch.addEventListener('input',(e)=>{
    const val = convertTurkishToBasic(e.target.value.toLowerCase());
    const filtered = customersFromDB.filter(c => {
      const fullName = convertTurkishToBasic((c.ad + ' ' + (c.soyad || '')).toLowerCase());
      return fullName.includes(val);
    });
    renderCustomerList(filtered);
  });
  }
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

// Ürünleri arama terimlerine göre filtrele (Bu fonksiyon filterAndRenderProducts içinde kullanılıyor)
function filterProductsBySearch() {
  if (!searchValue) {
    filteredProducts = [...products]; // Start with all products if no search term
    return; // Return early, other filters will apply in filterAndRenderProducts
  }
  
  const searchTerms = searchValue.toLowerCase().split(' ')
    .filter(term => term.length > 0)
    .map(term => convertTurkishToBasic(term));
  
  // This function will be called by filterAndRenderProducts, 
  // so it should operate on the current set of products being filtered.
  // For now, let filterAndRenderProducts handle the full logic.
  // This specific filterProductsBySearch might need to be integrated better if used standalone.
  // For simplicity, filterAndRenderProducts now incorporates this logic.
}


function renderCustomerList(lst=customersFromDB){
  const cList=document.getElementById('customerList');
  
  // Müşteri listesi boşsa bilgi mesajı göster
  if (lst.length === 0) {
    cList.innerHTML = '<div class="p-4 text-gray-500 text-center">Müşteri bulunamadı</div>';
    return;
  }
  
  cList.innerHTML = lst.map(m=>`
    <button 
      onclick="selectCustomer(${m.id})"
      class="block w-full text-left p-3 hover:bg-gray-50 rounded-lg border-b border-gray-100"
    >
      <div class="font-medium">${m.ad} ${m.soyad||''}</div>
      <div class="text-sm text-gray-500" id="customerBalance_${m.id}">Bakiye yükleniyor...</div>
    </button>
  `).join('');
  
  // Bakiye bilgisini AJAX ile getir
  lst.forEach(m => {
    fetchCustomerBalance(m.id);
  });
}

function toggleCustomerModal(){
  const mod=document.getElementById('customerModal');
  mod.classList.toggle('hidden');
  mod.classList.toggle('show');
  
  // Modalın z-index değerini sepetten yüksek tutmak için
  document.querySelectorAll('.modal').forEach(modal => {
    if (modal.id !== 'customerModal') {
      modal.style.zIndex = '55';
    } else {
      modal.style.zIndex = '60';
    }
  });
}

function selectCustomer(id){
  const found = customersFromDB.find(x => x.id == id);
  if(!found) return;
  selectedCustomer = found;
  
  // Müşteri adı ve bakiye bilgisini göster
  const selectedCustomerNameDetail = document.getElementById('selectedCustomerNameDetail');
  if (selectedCustomerNameDetail) {
    selectedCustomerNameDetail.textContent = `${found.ad} ${found.soyad || ''}`;
  }
  
  // Müşteri seçildi görünümüne geç
  const noCustomerSelected = document.getElementById('noCustomerSelected');
  const customerSelectedInfo = document.getElementById('customerSelectedInfo');
  
  if (noCustomerSelected && customerSelectedInfo) {
    noCustomerSelected.classList.add('hidden');
    customerSelectedInfo.classList.remove('hidden');
  }
  
  // AJAX ile güncel bakiyeyi getir
  fetch(`ajax_islem.php?islem=musteri_bakiye&id=${id}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        // Güncel bakiye değerlerini müşteri nesnesine ata
        found.cari_bakiye = data.try_bakiye;
        found.usd_bakiye = data.usd_bakiye;
        found.eur_bakiye = data.eur_bakiye;
        found.gbp_bakiye = data.gbp_bakiye;
        // Bakiye gösterimini güncelle
        updateCustomerBalance();
      }
    })
    .catch(error => {
      console.error('Bakiye çekilirken hata:', error);
      // Hata olursa da arayüzü güncelle
      updateCustomerBalance();
    });
  
  // Eski modalı kapat (geriye dönük uyumluluk için)
  const customerModal = document.getElementById('customerModal');
  if (customerModal) {
    customerModal.classList.add('hidden');
    customerModal.classList.remove('show');
  }
}

// Ürün List
function renderProducts() {
  const container = document.getElementById('productGrid');
  
  if (filteredProducts.length === 0) {
    container.innerHTML = `
      <div class="col-span-full p-8 text-center">
        <div class="text-gray-400 mb-2"><i class="ri-search-line text-3xl"></i></div>
        <h3 class="text-lg font-medium text-gray-700 mb-1">Ürün Bulunamadı</h3>
        <p class="text-gray-500 text-sm">Arama kriterlerinize uygun ürün bulunamadı.</p>
      </div>
    `;
    return;
  }
  
  // Her zaman grid görünümü kullanılacak
  container.innerHTML = filteredProducts.map(p => `
    <div class="product-card w-full bg-white rounded-lg shadow-sm overflow-hidden relative">
      <div class="relative mb-4">
        <div class="aspect-square rounded-lg overflow-hidden bg-gray-50 cursor-pointer" onclick="openImageViewer(${p.id})">
          ${p.resim_url ? 
            `<img 
              src="${p.resim_url}" 
            alt="${p.urun_adi}"
            class="w-full h-full object-contain"
              onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'100%\\' height=\\'100%\\' viewBox=\\'0 0 100 100\\'><rect width=\\'100%\\' height=\\'100%\\' fill=\\'%23f3f4f6\\'/><path d=\\'M30,40 L70,40 L70,60 L30,60 Z\\' fill=\\'%23d1d5db\\'/><path d=\\'M42,30 L58,30 L58,35 L42,35 Z\\' fill=\\'%23d1d5db\\'/><text x=\\'50%\\' y=\\'50%\\' dominant-baseline=\\'middle\\' text-anchor=\\'middle\\' font-family=\\'sans-serif\\' font-size=\\'10\\' fill=\\'%236b7280\\'>Resim Yok</text></svg>';"
            >` : 
            `<div class="w-full h-full flex items-center justify-center bg-gray-100">
              <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 100 100" class="text-gray-400">
                <rect width="100%" height="100%" fill="#f3f4f6"/>
                <path d="M30,40 L70,40 L70,60 L30,60 Z" fill="#d1d5db"/>
                <path d="M42,30 L58,30 L58,35 L42,35 Z" fill="#d1d5db"/>
                <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="10" fill="#6b7280">Resim Yok</text>
              </svg>
            </div>`
          }
        </div>
        
        <!-- Diğer resimler varsa küçük önizlemeler -->
        <div class="absolute bottom-2 left-2 flex gap-1">
          ${
            [p.resim_url_2, p.resim_url_3, p.resim_url_4, p.resim_url_5, 
             p.resim_url_6, p.resim_url_7, p.resim_url_8, p.resim_url_9, 
             p.resim_url_10]
             .filter(url => url)
             .map((url, idx) => `
              <div class="w-8 h-8 rounded bg-white shadow cursor-pointer" onclick="openImageViewer(${p.id}, ${idx + 1})">
                <img 
                  src="${url}" 
                  class="w-full h-full object-contain" 
                  alt=""
                  onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,<svg xmlns=\\'http://www.w3.org/2000/svg\\' width=\\'100%\\' height=\\'100%\\' viewBox=\\'0 0 100 100\\'><rect width=\\'100%\\' height=\\'100%\\' fill=\\'%23f3f4f6\\'/></svg>';"
                >
              </div>
              `).join('')
          }
        </div>

        <!-- Stok durumu etiketi -->
        <span class="absolute top-2 right-2 px-2 py-1 rounded-full text-xs sm:text-sm font-medium ${p.stok_miktari > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
          ${p.stok_miktari > 0 ? formatStockDisplay(p.stok_miktari, p.olcum_birimi) : 'Stokta Yok'}
        </span>
      </div>
      <div class="p-3">
        <div class="flex justify-between items-start mb-1">
          <h3 class="text-sm sm:text-base font-medium text-gray-800 line-clamp-2 leading-tight">${p.urun_adi}</h3>
          <span class="text-xs text-gray-500 whitespace-nowrap ml-2">Kod: ${p.urun_kodu || 'PRD' + p.id}</span>
        </div>
        <div class="flex justify-between items-center mb-2">
          <span class="text-primary font-bold">${formatCurrency(p.satis_fiyati)}</span>
          <span class="${p.stok_miktari > 0 ? 'text-green-600' : 'text-red-600'} text-sm">
            ${p.stok_miktari > 0 ? `Stok: ${formatStockDisplay(p.stok_miktari, p.olcum_birimi)}` : 'Stokta Yok'}
          </span>
        </div>
      </div>
      <div class="flex items-center gap-2 p-3 ${p.stok_miktari <= 0 ? 'opacity-50' : ''}">
        ${p.olcum_birimi === 'adet' ? `
          <div class="flex items-center w-full">
            <button 
              onclick="decreaseDirectQty(${p.id})"
              class="px-2 sm:px-3 py-1 sm:py-2 bg-gray-100 rounded-l hover:bg-gray-200"
              ${p.stok_miktari <= 0 ? 'disabled' : ''}
            >-</button>
            <input 
              type="number" 
              id="directQty_${p.id}"
              class="w-full px-2 sm:px-3 py-1 sm:py-2 border-y text-center text-sm sm:text-base" 
              value="0" 
              min="0" 
              max="${p.stok_miktari}"
              onchange="validateAndAddToCart(${p.id})"
              onfocus="this.select()"
              ${p.stok_miktari <= 0 ? 'disabled' : ''}
            >
            <button 
              onclick="increaseDirectQty(${p.id})"
              class="px-2 sm:px-3 py-1 sm:py-2 bg-gray-100 rounded-r hover:bg-gray-200"
              ${p.stok_miktari <= 0 ? 'disabled' : ''}
            >+</button>
          </div>
        ` : `
          <div class="flex items-center gap-1 w-full">
            <input 
              type="number" 
              id="directQty_${p.id}"
              class="w-full px-2 sm:px-3 py-1 sm:py-2 border rounded text-center text-sm sm:text-base" 
              value="0" 
              min="1" 
              step="1"
              max="${p.olcum_birimi === 'kg' ? parseFloat(p.stok_miktari) * 1000 : parseFloat(p.stok_miktari)}"
              onchange="validateAndAddToCartWeight(${p.id})"
              onfocus="this.select()"
              ${p.stok_miktari <= 0 ? 'disabled' : ''}
            >
            <span class="px-2 sm:px-3 py-1 sm:py-2 border bg-gray-50 text-sm sm:text-base">gr</span>
          </div>
        `}
      </div>
    </div>
  `).join('');
}

// Sepet
function toggleCart(){
  const c=document.getElementById('cartSidebar');
  c.classList.toggle('hidden');
  c.classList.toggle('show');
}

function addToCart(productIdOrProduct) {
    let productId = productIdOrProduct;
    if (typeof productIdOrProduct === 'object' && productIdOrProduct !== null && productIdOrProduct.id !== undefined) {
        productId = productIdOrProduct.id;
    }
    productId = parseInt(productId);

    const product = products.find(p => p.id === productId);
    if (!product) {
        alert('Ürün bulunamadı!');
    return;
  }
    
    if (product.stok_miktari < 1 && product.olcum_birimi === 'adet') { // Stok kontrolü adet ürünler için
        alert('Bu ürün stokta yok!');
        return;
    }
     if (product.stok_miktari <= 0 && product.olcum_birimi !== 'adet') { // Stok kontrolü ağırlık ürünler için
        alert('Bu ürün stokta yok!');
        return;
    }
    
    // Adet seçimi için modal göster
    showQtyModal(productId);
}

// Adet Seçim Modal
let selectedProductId = null;

function showQtyModal(productId) {
    selectedProductId = parseInt(productId);
    const product = products.find(p => p.id === selectedProductId);
    
    // Varolan modalı temizle
    const existingModal = document.getElementById('qtyModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ölçüm birimine göre farklı içerik göster
    let qtyInputHtml = '';
    let unitLabel = '';
    
    if (product.olcum_birimi === 'adet') {
        qtyInputHtml = `
            <div class="flex items-center">
                <button onclick="decreaseModalQty()" class="px-3 py-1 bg-gray-100 rounded-l hover:bg-gray-200">-</button>
                <input 
                    type="number" 
                    id="modalQty" 
                    class="w-20 px-3 py-1 border-y text-center" 
                    value="1" 
                    min="1" 
                    max="${product.stok_miktari}"
                    onchange="validateModalQty()"
                >
                <button onclick="increaseModalQty()" class="px-3 py-1 bg-gray-100 rounded-r hover:bg-gray-200">+</button>
            </div>
        `;
        unitLabel = 'Adet:';
    } else if (product.olcum_birimi === 'kg' || product.olcum_birimi === 'gr') {
        const currentStockInKg = product.olcum_birimi === 'gr' ? product.stok_miktari / 1000 : product.stok_miktari;
        const currentStockInGr = product.olcum_birimi === 'kg' ? product.stok_miktari * 1000 : product.stok_miktari;

        qtyInputHtml = `
            <div class="flex flex-col space-y-2">
                <div class="flex items-center">
                    <input 
                        type="number" 
                        id="modalQty" 
                        class="w-24 px-3 py-1 border rounded-l text-center" 
                        value="100" 
                        min="1" 
                        max="${currentStockInGr}"
                        step="1"
                        onchange="validateModalQty()"
                    >
                    <select id="weightUnit" class="border border-l-0 rounded-r px-2 py-1 bg-gray-50" onchange="updateWeightUnit()">
                        <option value="gr" selected>gr</option>
                        <option value="kg">kg</option>
                    </select>
                </div>
                <div class="text-xs text-gray-500">
                    Stok: ${parseFloat(currentStockInKg).toFixed(3)} kg (${parseFloat(currentStockInGr).toFixed(0)} gr)
                </div>
            </div>
        `;
        unitLabel = 'Miktar:';
    }
    
    // Modal HTML
    const modalHtml = `
        <div id="qtyModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
            <div class="w-full max-w-md bg-white rounded-lg shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium">${product.urun_adi}</h3>
                    <button onclick="closeQtyModal()" class="p-2 hover:bg-gray-100 rounded-full">
                        <i class="ri-close-line ri-lg"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Mevcut Stok: ${formatStockDisplay(product.stok_miktari, product.olcum_birimi)}</p>
                    <div class="flex items-center gap-2">
                        <label class="text-sm font-medium">${unitLabel}</label>
                        ${qtyInputHtml}
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button onclick="closeQtyModal()" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-button">
                        İptal
                    </button>
                    <button onclick="confirmAddToCart()" class="px-4 py-2 bg-primary text-white text-sm rounded-button">
                        Sepete Ekle
                    </button>
                </div>
            </div>
        </div>
    `;
    
    // Modal'ı sayfaya ekle
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    if (product.olcum_birimi !== 'adet') { // Eğer ağırlık ürünü ise, birim seçicisini ayarla
        updateWeightUnit(); // To set initial step/min/max based on default unit (gr)
    }
}

function closeQtyModal() {
    const modal = document.getElementById('qtyModal');
    if (modal) {
        modal.remove();
    }
    selectedProductId = null;
}

function validateModalQty() {
    const input = document.getElementById('modalQty');
    const product = products.find(p => p.id === selectedProductId);
    
    if (product.olcum_birimi === 'adet') {
        let value = parseInt(input.value) || 0;
        if (value < 1) value = 1;
        if (value > product.stok_miktari) value = product.stok_miktari;
        input.value = value;
    } else { // kg or gr
        const unitSelect = document.getElementById('weightUnit');
        const selectedUnit = unitSelect.value;
        let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        
        let stockInSelectedUnit;
        if (selectedUnit === 'kg') {
            stockInSelectedUnit = product.olcum_birimi === 'gr' ? product.stok_miktari / 1000 : product.stok_miktari;
        if (value < 0.001) value = 0.001;
        } else { // gr
            stockInSelectedUnit = product.olcum_birimi === 'kg' ? product.stok_miktari * 1000 : product.stok_miktari;
             if (value < 1) value = 1;
        }

        if (value > stockInSelectedUnit) value = stockInSelectedUnit;
        input.value = value;
    }
}

function updateWeightUnit() {
    const weightUnitSelect = document.getElementById('weightUnit');
    if (!weightUnitSelect) return; // Modal not open or not a weight product
    const selectedUnit = weightUnitSelect.value;
    const qtyInput = document.getElementById('modalQty');
    const product = products.find(p => p.id === selectedProductId);
    
    let currentValue = parseFloat(qtyInput.value.replace(/\./g, '').replace(',', '.')) || 0;
    
    // Determine stock in native unit of product
    const stockNative = parseFloat(product.stok_miktari);
    
    if (selectedUnit === 'kg') {
        qtyInput.step = "0.001";
        qtyInput.min = "0.001";
        const stockInKg = product.olcum_birimi === 'gr' ? stockNative / 1000 : stockNative;
        qtyInput.max = stockInKg.toFixed(3);
        // Convert current value if it was in gr
        // Example: if input had 500 (gr) and user switches to kg, it should become 0.5
        // This part is tricky because we don't know the "previous" unit of the input value itself easily.
        // For simplicity, we'll assume a common starting point or just validate against new max.
        // qtyInput.value = (currentValue / 1000).toFixed(3); // If we assume currentValue was gr
    } else { // gr
        qtyInput.step = "1";
        qtyInput.min = "1";
        const stockInGr = product.olcum_birimi === 'kg' ? stockNative * 1000 : stockNative;
        qtyInput.max = stockInGr.toFixed(0);
        // qtyInput.value = (currentValue * 1000).toFixed(0); // If we assume currentValue was kg
    }
    validateModalQty(); // Re-validate with new constraints
}


function decreaseModalQty() {
    const input = document.getElementById('modalQty');
    const product = products.find(p => p.id === selectedProductId);
    
    if (product.olcum_birimi === 'adet') {
        let value = parseInt(input.value) || 1;
        if (value > 1) {
            input.value = value - 1;
        }
    } else {
        let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        const selectedUnit = document.getElementById('weightUnit').value;
        let step = selectedUnit === 'kg' ? 0.001 : 1; // More granular steps
        let minVal = selectedUnit === 'kg' ? 0.001 : 1;
        
        if (value - step >= minVal) {
             input.value = selectedUnit === 'kg' ? (value - step).toFixed(3) : (value - step).toFixed(0);
        } else {
            input.value = minVal;
        }
    }
    validateModalQty();
}

function increaseModalQty() {
    const input = document.getElementById('modalQty');
    const product = products.find(p => p.id === selectedProductId);
    
    if (product.olcum_birimi === 'adet') {
        let value = parseInt(input.value) || 0;
        if (value < product.stok_miktari) {
            input.value = value + 1;
        }
    } else {
        let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        const selectedUnit = document.getElementById('weightUnit').value;
        let step = selectedUnit === 'kg' ? 0.001 : 1; // More granular steps
        
        let stockInSelectedUnit;
        if (selectedUnit === 'kg') {
            stockInSelectedUnit = product.olcum_birimi === 'gr' ? product.stok_miktari / 1000 : product.stok_miktari;
        } else { // gr
            stockInSelectedUnit = product.olcum_birimi === 'kg' ? product.stok_miktari * 1000 : product.stok_miktari;
        }

        if (value + step <= stockInSelectedUnit) {
            input.value = selectedUnit === 'kg' ? (value + step).toFixed(3) : (value + step).toFixed(0);
        } else {
            input.value = stockInSelectedUnit;
        }
    }
    validateModalQty();
}

function confirmAddToCart() {
    const product = products.find(p => p.id === selectedProductId);
    if (!product) {
        alert('Ürün bulunamadı!');
        return;
    }
    
    const qtyInput = document.getElementById('modalQty');
    let qty = 0;
    let itemOlcumBirimi = product.olcum_birimi; // Default to product's native unit
    let itemPrice = parseFloat(product.satis_fiyati);
    
    if (product.olcum_birimi === 'adet') {
        qty = parseInt(qtyInput.value) || 1;
    } else { // kg or gr
        qty = parseFloat(qtyInput.value.replace(/\./g, '').replace(',', '.')) || 0.1;
        const selectedUnit = document.getElementById('weightUnit').value;
        itemOlcumBirimi = selectedUnit; // Use the unit selected in the modal (kg or gr)

        // Adjust price based on the unit of sale (itemOlcumBirimi) vs product's native unit (product.olcum_birimi)
        if (itemOlcumBirimi === 'gr') {
            if (product.olcum_birimi === 'kg') { // Product native is kg, selling in gr
                itemPrice = parseFloat(product.satis_fiyati) / 1000; // Price per gram
            } else { // Product native is gr, selling in gr
                itemPrice = parseFloat(product.satis_fiyati); // Price per gram
            }
        } else if (itemOlcumBirimi === 'kg') {
            if (product.olcum_birimi === 'gr') { // Product native is gr, selling in kg
                itemPrice = parseFloat(product.satis_fiyati) * 1000; // Price per kg
            } else { // Product native is kg, selling in kg
                itemPrice = parseFloat(product.satis_fiyati); // Price per kg
            }
        }
    }
    
    // Stok kontrolü - convert qty to product's native unit for comparison
    let qtyInNativeUnit = qty;
    if (product.olcum_birimi === 'kg' && itemOlcumBirimi === 'gr') {
        qtyInNativeUnit = qty / 1000;
    } else if (product.olcum_birimi === 'gr' && itemOlcumBirimi === 'kg') {
        qtyInNativeUnit = qty * 1000;
    }

    if (qtyInNativeUnit > product.stok_miktari) {
        alert('Yetersiz stok!');
        // Potentially reset input value to max available in selected unit
        validateModalQty(); // This should cap it.
        return;
    }
    
    // Sepette var mı kontrol et
    const existingItem = cart.find(item => item.id === selectedProductId && item.olcumBirimi === itemOlcumBirimi);
    if (existingItem) {
        existingItem.qty += qty; // Add to existing quantity if same unit
        // Re-check stock for combined quantity
        let totalQtyInNativeUnit = existingItem.qty;
         if (product.olcum_birimi === 'kg' && itemOlcumBirimi === 'gr') {
            totalQtyInNativeUnit = existingItem.qty / 1000;
        } else if (product.olcum_birimi === 'gr' && itemOlcumBirimi === 'kg') {
            totalQtyInNativeUnit = existingItem.qty * 1000;
        }
        if (totalQtyInNativeUnit > product.stok_miktari) {
            alert('Sepetteki miktarla birlikte yetersiz stok!');
            existingItem.qty -= qty; // Revert addition
            return;
        }

    } else {
        // If item with different unit exists, or item not in cart, add as new/separate
        cart.push({
            id: product.id,
            name: product.urun_adi,
            price: itemPrice, // Price per unit of itemOlcumBirimi
            qty: qty,
            olcumBirimi: itemOlcumBirimi, // Unit of sale (kg or gr or adet)
            note: '' // Initialize note
        });
    }
    
    updateCartCount();
    renderCart();
    toggleCart();
    closeQtyModal();
}

function removeFromCart(productId, itemIndex) { // Added itemIndex to remove specific item if multiple of same product with diff units
    if (itemIndex !== undefined) {
         cart.splice(itemIndex, 1);
    } else { // Fallback for older calls, might remove the first one found
    const index = cart.findIndex(item => item.id === productId);
    if (index === -1) return;
    cart.splice(index, 1);
    }
    updateCartCount();
    renderCart();
}

function clearCart(){
  if(confirm("Sepeti temizlemek istiyor musunuz?")){
    cart=[];
    updateCartCount(); // Saves empty cart
    
    document.getElementById('orderNote').value = '';
    localStorage.removeItem('orderNoteSatis');

    document.getElementById('discountRate').value = '';
    localStorage.removeItem('discountRateSatis');
    
    renderCart(); // Renders empty cart and recalculates totals (which calls calculateTotal)
  }
}

function updateCartCount(){
  const uniqueItems = cart.length; 
  document.getElementById('cartCount').textContent = uniqueItems;
  saveCartToLocalStorage(); 
}

function renderCart(){
  const cItems=document.getElementById('cartItems');
  cItems.innerHTML= cart.map((item, index) =>`
    <div class="flex gap-3 p-3 bg-gray-50 rounded-lg">
      <div class="flex-1">
        <h4 class="font-medium text-xs">${item.name} (${item.olcumBirimi})</h4>
        <p class="text-xs text-gray-500">Birim Fiyat: ${formatCurrency(item.price)} / ${item.olcumBirimi}</p>
        <p class="text-xs text-gray-500">Kod: PRD${String(item.id).padStart(4, '0')}</p>
        ${
          item.note
            ? `<button class="text-xs text-blue-500 mt-1" onclick="openItemNoteModal(${index})">${item.note}</button>`
            : `<button class="text-xs text-blue-500 mt-1" onclick="openItemNoteModal(${index})">Not Düzenle</button>`
        }
      </div>
      <div class="flex flex-col items-end justify-between">
        <div class="flex items-center h-8">
          <button class="h-full px-2 bg-gray-200 rounded-l flex items-center justify-center" onclick="decreaseQty(${index})">-</button>
          <input 
            type="number"
            class="w-12 h-full text-center border-y text-xs"
            value="${item.qty}"
            onchange="updateQty(${index}, this.value)"
            ${item.olcumBirimi === 'kg' ? 'step="0.001" min="0.001"' : (item.olcumBirimi === 'gr' ? 'step="1" min="1"' : 'min="1"')}
          >
          ${item.olcumBirimi !== 'adet' ? 
            `<span class="h-full px-1 flex items-center border-t border-b bg-gray-50 text-xs">${item.olcumBirimi}</span>` : 
            ''}
          <button class="h-full px-2 bg-gray-200 rounded-r flex items-center justify-center" onclick="increaseQty(${index})">+</button>
        </div>
        <button class="p-1 text-red-500 hover:bg-red-50 rounded" onclick="removeFromCart(${item.id}, ${index})">
          <i class="ri-delete-bin-line"></i>
        </button>
      </div>
    </div>
  `).join('') || '<div class="text-center py-8 text-gray-500">Sepetinizde ürün bulunmuyor.</div>';
  
  calculateTotal(); // Recalculate and display totals
  updateCustomerBalance(); // Müşteri bakiyesini güncelle
  saveCartToLocalStorage();
}

// Müşteri bakiyesini güncelle
function updateCustomerBalance() {
  const balanceElement = document.getElementById('customerBalance');
  const noCustomerSelected = document.getElementById('noCustomerSelected');
  const customerSelectedInfo = document.getElementById('customerSelectedInfo');
  const selectedCustomerNameDetail = document.getElementById('selectedCustomerNameDetail');
  
  if (selectedCustomer && selectedCustomer.id) {
    // TRY bakiye
    const bakiye = parseFloat(selectedCustomer.cari_bakiye) || 0;
    
    // Bakiye HTML hazırla
    let bakiyeHtml = `<div class="${bakiye < 0 ? 'text-green-500' : (bakiye > 0 ? 'text-red-500' : 'text-gray-500')}">
      TRY: ${formatCurrency(bakiye)}
    </div>`;
    
    // Döviz bakiyelerini ekle (varsa)
    if (selectedCustomer.usd_bakiye && selectedCustomer.usd_bakiye !== 0) {
      const usdBakiye = parseFloat(selectedCustomer.usd_bakiye);
      bakiyeHtml += `<div class="${usdBakiye < 0 ? 'text-green-500' : (usdBakiye > 0 ? 'text-red-500' : 'text-gray-500')}">
        USD: ${Math.abs(usdBakiye).toLocaleString('tr-TR', {minimumFractionDigits: 2})} $
      </div>`;
    }
    
    if (selectedCustomer.eur_bakiye && selectedCustomer.eur_bakiye !== 0) {
      const eurBakiye = parseFloat(selectedCustomer.eur_bakiye);
      bakiyeHtml += `<div class="${eurBakiye < 0 ? 'text-green-500' : (eurBakiye > 0 ? 'text-red-500' : 'text-gray-500')}">
        EUR: ${Math.abs(eurBakiye).toLocaleString('tr-TR', {minimumFractionDigits: 2})} €
      </div>`;
    }
    
    if (selectedCustomer.gbp_bakiye && selectedCustomer.gbp_bakiye !== 0) {
      const gbpBakiye = parseFloat(selectedCustomer.gbp_bakiye);
      bakiyeHtml += `<div class="${gbpBakiye < 0 ? 'text-green-500' : (gbpBakiye > 0 ? 'text-red-500' : 'text-gray-500')}">
        GBP: ${Math.abs(gbpBakiye).toLocaleString('tr-TR', {minimumFractionDigits: 2})} £
      </div>`;
    }
    
    // Bakiye HTML'i göster
    if (balanceElement) balanceElement.innerHTML = bakiyeHtml;
    
    // Müşteri adını detay alanına ekle
    if (selectedCustomerNameDetail) {
      selectedCustomerNameDetail.textContent = `${selectedCustomer.ad} ${selectedCustomer.soyad || ''}`;
    }
    
    // Müşteri seçildi view'a geç
    if (noCustomerSelected && customerSelectedInfo) {
      noCustomerSelected.classList.add('hidden');
      customerSelectedInfo.classList.remove('hidden');
    }
  } else {
    // Müşteri seçilmedi - varsayılan değerler
    if (balanceElement) {
      balanceElement.innerHTML = '<div class="text-gray-500">0,00 ₺</div>';
    }
    
    // Müşteri seçilmedi view'a geç
    if (noCustomerSelected && customerSelectedInfo) {
      noCustomerSelected.classList.remove('hidden');
      customerSelectedInfo.classList.add('hidden');
    }
  }
}

function updateQty(itemIndex, value) {
  const item = cart[itemIndex];
  if (!item) return;
  
  const product = products.find(p => p.id === item.id);
  if (!product) return;

  let newQty = 0;
  let maxQtyNative = parseFloat(product.stok_miktari); // Max quantity in product's native unit
  
  if (item.olcumBirimi === 'adet') {
    newQty = parseInt(value) || 1;
    if (newQty < 1) newQty = 1;
    if (newQty > maxQtyNative) newQty = maxQtyNative;
  } else { // kg or gr
    newQty = parseFloat(value) || (item.olcumBirimi === 'kg' ? 0.001 : 1);
    let minVal = item.olcumBirimi === 'kg' ? 0.001 : 1;
    if (newQty < minVal) newQty = minVal;

    // Convert newQty to product's native unit for stock check
    let newQtyInNativeUnit = newQty;
    if (product.olcum_birimi === 'kg' && item.olcumBirimi === 'gr') {
        newQtyInNativeUnit = newQty / 1000;
    } else if (product.olcum_birimi === 'gr' && item.olcumBirimi === 'kg') {
        newQtyInNativeUnit = newQty * 1000;
    }
    
    if (newQtyInNativeUnit > maxQtyNative) {
        // Convert maxQtyNative back to item's olcumBirimi for display
        if (product.olcum_birimi === 'kg' && item.olcumBirimi === 'gr') {
            newQty = maxQtyNative * 1000;
        } else if (product.olcum_birimi === 'gr' && item.olcumBirimi === 'kg') {
            newQty = maxQtyNative / 1000;
  } else {
            newQty = maxQtyNative;
    }
  }
  }
  item.qty = newQty;
  renderCart();
}
  
function increaseQty(itemIndex) {
  const item = cart[itemIndex];
  if (!item) return;
  const product = products.find(p => p.id === item.id);
  if (!product) return;
  
  let currentQty = item.qty;
  let step = 1;
  if (item.olcumBirimi === 'kg') step = 0.001;
  else if (item.olcumBirimi === 'gr') step = 1;

  let newQty = parseFloat((currentQty + step).toFixed(item.olcumBirimi === 'kg' ? 3 : 0));

  // Check stock (convert newQty to product's native unit)
  let newQtyInNativeUnit = newQty;
  if (product.olcum_birimi === 'kg' && item.olcumBirimi === 'gr') newQtyInNativeUnit = newQty / 1000;
  if (product.olcum_birimi === 'gr' && item.olcumBirimi === 'kg') newQtyInNativeUnit = newQty * 1000;
  
  if (newQtyInNativeUnit <= product.stok_miktari) {
    item.qty = newQty;
  } else {
    // Optionally alert or set to max possible
    // For now, just don't increase if it exceeds stock
    }
  renderCart();
}

function decreaseQty(itemIndex) {
  const item = cart[itemIndex];
  if (!item) return;
  
  let currentQty = item.qty;
  let step = 1;
  let minVal = 1;
  
  if (item.olcumBirimi === 'kg') { step = 0.001; minVal = 0.001; }
  else if (item.olcumBirimi === 'gr') { step = 1; minVal = 1; }
  
  if (currentQty - step >= minVal) {
    item.qty = parseFloat((currentQty - step).toFixed(item.olcumBirimi === 'kg' ? 3 : 0));
  } else {
    item.qty = minVal;
    }
  renderCart();
}

function calculateTotal(){
  let subtotal=0;
  cart.forEach(item => subtotal += (item.price * item.qty));
  
  const discountRateInput = document.getElementById('discountRate');
  const discountRate = discountRateInput && discountRateInput.value ? parseFloat(discountRateInput.value) : 0;
  
  const discountAmount = subtotal * (discountRate / 100);
  const total = subtotal - discountAmount;
  
  // Detay alanındaki değerleri güncelle
  if (document.getElementById('subtotal')) {
    document.getElementById('subtotal').textContent = formatCurrency(subtotal);
  }
  
  if (document.getElementById('discountAmount')) {
    document.getElementById('discountAmount').textContent = formatCurrency(discountAmount);
  }
  
  if (document.getElementById('total')) {
    document.getElementById('total').textContent = formatCurrency(total);
  }
  
  // Ana özet alanındaki toplam tutarı güncelle
  const totalSummaryElement = document.getElementById('totalSummary');
  if (totalSummaryElement) {
    totalSummaryElement.textContent = formatCurrency(total);
  }
}

// Filtre
function toggleFilter(){
  const fm = document.getElementById('filterModal');
  fm.classList.toggle('hidden');
  fm.classList.toggle('show');
}

function resetFilters(){
  document.getElementById('minPrice').value = '';
  document.getElementById('maxPrice').value = '';
  document.getElementById('showOutOfStock').checked = false;
  showOutOfStock = false;
  minPrice = 0;
  maxPrice = Infinity;
  
  saveFilterStateToLocalStorage();
  filterAndRenderProducts();
}

function applyFilter(){
  minPrice = parseFloat(document.getElementById('minPrice').value) || 0;
  maxPrice = parseFloat(document.getElementById('maxPrice').value) || Infinity;
  showOutOfStock = document.getElementById('showOutOfStock').checked;
  
  saveFilterStateToLocalStorage();
  filterAndRenderProducts();
  toggleFilter();
}

// Sıralama
function toggleSort(){
  const sm = document.getElementById('sortModal');
  sm.classList.toggle('hidden');
  sm.classList.toggle('show');
}

function sortProducts(type){
  switch(type){
    case 'name':
      filteredProducts.sort((a,b) => a.urun_adi.localeCompare(b.urun_adi, 'tr'));
      break;
    case 'name-desc':
      filteredProducts.sort((a,b) => b.urun_adi.localeCompare(a.urun_adi, 'tr'));
      break;
    case 'price':
      filteredProducts.sort((a,b) => parseFloat(a.satis_fiyati) - parseFloat(b.satis_fiyati));
      break;
    case 'price-desc':
      filteredProducts.sort((a,b) => parseFloat(b.satis_fiyati) - parseFloat(a.satis_fiyati));
      break;
    case 'stock':
      filteredProducts.sort((a,b) => parseFloat(a.stok_miktari) - parseFloat(b.stok_miktari));
      break;
    case 'stock-desc':
      filteredProducts.sort((a,b) => parseFloat(b.stok_miktari) - parseFloat(a.stok_miktari));
      break;
  }
  renderProducts();
  toggleSort();
}

// Ürünleri filtrele ve göster
function filterAndRenderProducts() {
  let tempFiltered = [...products]; 
  
  // Arama filtresi
  if (searchValue) {
    const searchTerms = searchValue.toLowerCase().split(' ')
      .filter(term => term.length > 0)
      .map(term => convertTurkishToBasic(term));
    
    tempFiltered = tempFiltered.filter(product => {
      const searchableText = convertTurkishToBasic([
        product.urun_adi || '',
        product.urun_kodu || '',
        product.barkod || '',
        `${product.stok_miktari} ${product.olcum_birimi}`,
        formatCurrency(product.satis_fiyati)
      ].filter(Boolean).join(' ').toLowerCase());
      
      return searchTerms.every(term => searchableText.includes(term));
    });
  }
  
  // Fiyat ve stok filtreleri
  filteredProducts = tempFiltered.filter(product => {
    const price = parseFloat(product.satis_fiyati);
    const inStockFilter = showOutOfStock ? true : parseFloat(product.stok_miktari) > 0;
    return price >= minPrice && price <= maxPrice && inStockFilter;
  });
  
  renderProducts();
}

// Sipariş Notu
function showOrderNoteModal(){
  const n=document.getElementById('orderNoteModal');
  n.classList.remove('hidden');
  n.classList.add('show');
}
function toggleOrderNoteModal(){
  const n=document.getElementById('orderNoteModal');
  n.classList.toggle('hidden');
  n.classList.toggle('show');
}
function saveOrderNote(){
  saveOrderNoteToLocalStorage(); // Save to localStorage
  toggleOrderNoteModal();
  calculateTotal(); // Recalculate total
}

// İskonto Modal
function toggleDiscountModal() {
  const modal = document.getElementById('discountModal');
  if (modal) {
  modal.classList.toggle('hidden');
  modal.classList.toggle('show');
  }
}

function applyDiscount() {
  const discountRateInput = document.getElementById('discountRate');
  if (discountRateInput) {
  saveDiscountRateToLocalStorage();
  calculateTotal();
  toggleDiscountModal();
  }
}

// Ürün Notu
let currentItemNoteIndex=null; // Use index for cart items
function openItemNoteModal(itemIndex){
  currentItemNoteIndex = itemIndex;
  const item = cart[itemIndex];
  document.getElementById('itemNote').value = item && item.note ? item.note : '';
  const modal=document.getElementById('itemNoteModal');
  modal.classList.remove('hidden');
  modal.classList.add('show');
}
function toggleItemNoteModal(){
  const mo=document.getElementById('itemNoteModal');
  mo.classList.toggle('hidden');
  mo.classList.toggle('show');
}
function saveItemNote(){
  const val=document.getElementById('itemNote').value;
  let item = cart[currentItemNoteIndex];
  if(item) item.note=val;
  toggleItemNoteModal();
  renderCart(); // This will also save the cart with the updated note
}

// Siparişi Tamamla
function completeOrder(){
  if(!selectedCustomer){
    alert("Lütfen müşteri seçmeden devam edemezsiniz!");
    return;
  }
  if(cart.length===0){
    alert("Sepet boş!");
    return;
  }
  const discountRate= parseFloat(document.getElementById('discountRate').value)||0;
  const note= document.getElementById('orderNote').value||'';
  const postData={
    musteri_id: selectedCustomer.id,
    items: cart,
    discountRate,
    note
  };

  fetch('satis_kaydet.php',{
    method:'POST',
    headers:{ 'Content-Type':'application/json' },
    body: JSON.stringify(postData)
  })
  .then(async r => {
    if(!r.ok){
      const errorText = await r.text();
      console.error('HTTP Hatası:', r.status, errorText);
      throw new Error('HTTP Hatası: ' + r.status + ' - ' + errorText);
    }
    try {
      return await r.json();
    } catch (e) {
      console.error('JSON parse hatası:', e);
      const responseText = await r.text(); // Get raw response
      console.error('Ham yanıt:', responseText);
      throw new Error('JSON parse hatası: ' + e.message + '. Yanıt: ' + responseText);
    }
  })
  .then(resp=>{
    console.log('Sunucu yanıtı:', resp);
    if(resp.success){
      alert("Sipariş başarıyla kaydedildi! Fatura No: " + resp.fatura_id);
      cart=[];
      updateCartCount(); // Saves empty cart
      
      document.getElementById('orderNote').value='';
      localStorage.removeItem('orderNoteSatis');

      document.getElementById('discountRate').value = '';
      localStorage.removeItem('discountRateSatis');
      
      renderCart(); // Renders empty cart & recalculates totals
    } else {
      console.error('Sipariş hatası:', resp);
      alert("Sipariş kaydedilirken hata: "+(resp.message||'Bilinmeyen hata'));
      if (resp.error) {
        console.error('Detaylı hata:', resp.error);
      }
    }
  })
  .catch(err=>{
    console.error('Fetch hatası:', err);
    alert("Sunucu hatası: "+err.message);
  });
}

// Direkt sepete ekleme fonksiyonları (Ürün kartındaki +/- butonları için)
function validateAndAddToCart(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    
    if (product.olcum_birimi !== 'adet') {
        validateAndAddToCartWeight(productId); // Delegate to weight function if not 'adet'
        return;
    }
    
    let value = parseInt(input.value) || 0;
    if (value < 0) value = 0; // Cannot be negative
    if (value > product.stok_miktari) value = product.stok_miktari; // Cap at stock
    
    input.value = value; // Update input field with validated value

    const existingItemIndex = cart.findIndex(item => item.id === productId && item.olcumBirimi === 'adet');
    
    if (value === 0) {
        if (existingItemIndex !== -1) {
            cart.splice(existingItemIndex, 1); // Remove if quantity is zero
        }
    } else {
        if (existingItemIndex !== -1) {
            cart[existingItemIndex].qty = value; // Update quantity
        } else {
            cart.push({ // Add new item
            id: product.id,
            name: product.urun_adi,
            price: parseFloat(product.satis_fiyati),
            qty: value,
                olcumBirimi: 'adet',
                note: ''
        });
        }
    }
    
    updateCartCount();
    renderCart();
}

function decreaseDirectQty(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    if (product.olcum_birimi !== 'adet') return; // Only for 'adet' type
    
    let value = parseInt(input.value) || 0;
    if (value > 0) {
        input.value = value - 1;
        validateAndAddToCart(productId);
    }
}

function increaseDirectQty(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    if (product.olcum_birimi !== 'adet') return; // Only for 'adet' type
    
    let value = parseInt(input.value) || 0;
    if (value < product.stok_miktari) {
        input.value = value + 1;
        validateAndAddToCart(productId);
    }
}


// Global değişkenler ekleyelim
let currentProduct = null;
let currentImageIndex = 0;

// Resim görüntüleyici fonksiyonları
function openImageViewer(productId, imageIndex = 0) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    
    currentProduct = product;
    currentImageIndex = imageIndex;
    
    const images = [
        product.resim_url, product.resim_url_2, product.resim_url_3, product.resim_url_4, product.resim_url_5,
        product.resim_url_6, product.resim_url_7, product.resim_url_8, product.resim_url_9, product.resim_url_10
    ].filter(url => url);
    
    if (images.length === 0) {
        // Hiç resim yoksa özel bir SVG göster ve önizleme modunu açma
        return;
    }

    if (imageIndex >= images.length) imageIndex = 0;

    const viewerImage = document.getElementById('viewerImage');
    viewerImage.src = images[imageIndex];
    viewerImage.alt = product.urun_adi;
    viewerImage.onerror = function() {
        this.onerror = null;
        this.src = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="#f3f4f6"/><path d="M30,40 L70,40 L70,60 L30,60 Z" fill="#d1d5db"/><path d="M42,30 L58,30 L58,35 L42,35 Z" fill="#d1d5db"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="10" fill="#6b7280">Resim Yok</text></svg>');
    };

    const thumbContainer = document.getElementById('thumbContainer');
    thumbContainer.innerHTML = images.map((url, idx) => `
        <div class="w-16 h-16 bg-white/10 rounded cursor-pointer ${idx === imageIndex ? 'ring-2 ring-white' : ''}"
             onclick="changeImage(${idx})">
            <img src="${url}" 
                 alt="${product.urun_adi} - ${idx + 1}" 
                 class="w-full h-full object-contain rounded"
                 onerror="this.onerror=null; this.src='data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'100%\' height=\'100%\' viewBox=\'0 0 100 100\'><rect width=\'100%\' height=\'100%\' fill=\'#f3f4f6\'/></svg>');">
        </div>
    `).join('');

    const prevButton = document.getElementById('prevImage');
    const nextButton = document.getElementById('nextImage');
    
    if (prevButton && nextButton) {
        prevButton.style.display = images.length > 1 && imageIndex > 0 ? 'block' : 'none';
        nextButton.style.display = images.length > 1 && imageIndex < images.length - 1 ? 'block' : 'none';
        
        prevButton.onclick = () => changeImage((imageIndex - 1 + images.length) % images.length);
        nextButton.onclick = () => changeImage((imageIndex + 1) % images.length);
    }

    const modal = document.getElementById('imageViewerModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    document.addEventListener('keydown', handleKeyboardNavigation);
}

function changeImage(newIndex) {
    if (!currentProduct) return;
    
    const images = [
        currentProduct.resim_url, currentProduct.resim_url_2, currentProduct.resim_url_3, currentProduct.resim_url_4, currentProduct.resim_url_5,
        currentProduct.resim_url_6, currentProduct.resim_url_7, currentProduct.resim_url_8, currentProduct.resim_url_9, currentProduct.resim_url_10
    ].filter(url => url);
    
    if (images.length === 0) return;
    if (newIndex < 0 || newIndex >= images.length) newIndex = 0;
    
    currentImageIndex = newIndex;
    
    const viewerImage = document.getElementById('viewerImage');
    viewerImage.src = images[newIndex];
    viewerImage.onerror = function() {
        this.onerror = null;
        this.src = 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" viewBox="0 0 100 100"><rect width="100%" height="100%" fill="#f3f4f6"/><path d="M30,40 L70,40 L70,60 L30,60 Z" fill="#d1d5db"/><path d="M42,30 L58,30 L58,35 L42,35 Z" fill="#d1d5db"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="10" fill="#6b7280">Resim Yok</text></svg>');
    };

    const thumbs = document.querySelectorAll('#thumbContainer > div');
    thumbs.forEach((thumb, idx) => {
        thumb.classList.toggle('ring-2', idx === newIndex);
        thumb.classList.toggle('ring-white', idx === newIndex);
    });
    
    const prevButton = document.getElementById('prevImage');
    const nextButton = document.getElementById('nextImage');
    if (prevButton && nextButton) {
        prevButton.style.display = images.length > 1 && newIndex > 0 ? 'block' : 'none';
        nextButton.style.display = images.length > 1 && newIndex < images.length - 1 ? 'block' : 'none';
    }
}

function handleKeyboardNavigation(e) {
    const modal = document.getElementById('imageViewerModal');
    if (!modal || modal.classList.contains('hidden')) return;

    const images = [
        currentProduct.resim_url, currentProduct.resim_url_2, currentProduct.resim_url_3, currentProduct.resim_url_4, currentProduct.resim_url_5,
        currentProduct.resim_url_6, currentProduct.resim_url_7, currentProduct.resim_url_8, currentProduct.resim_url_9, currentProduct.resim_url_10
    ].filter(url => url);
    if (images.length === 0) return;


    switch (e.key) {
        case 'ArrowLeft':
            changeImage((currentImageIndex - 1 + images.length) % images.length);
            break;
        case 'ArrowRight':
            changeImage((currentImageIndex + 1) % images.length);
            break;
        case 'Escape':
            closeImageViewer();
            break;
    }
}

function closeImageViewer() {
    const modal = document.getElementById('imageViewerModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.removeEventListener('keydown', handleKeyboardNavigation);
    currentProduct = null;
}

// Ağırlık ürünleri için sepete ekleme fonksiyonu (direkt ürün kartından)
function validateAndAddToCartWeight(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    if (!product || product.olcum_birimi === 'adet') return; // Ensure it's a weight product

    let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0; // Value is always in grams from this input
    
    if (value < 1) value = 0; // Allow 0 to remove from cart

    let maxStockInGrams = product.olcum_birimi === 'kg' ? 
        parseFloat(product.stok_miktari) * 1000 : 
        parseFloat(product.stok_miktari);
    
    if (value > maxStockInGrams) {
        alert('Yetersiz stok!');
        value = maxStockInGrams;
    }
    input.value = value; // Update input with validated gram value

    // Price per gram
    let pricePerGram = product.olcum_birimi === 'kg' ?
        parseFloat(product.satis_fiyati) / 1000 :
        parseFloat(product.satis_fiyati);

    const existingItemIndex = cart.findIndex(item => item.id === productId && item.olcumBirimi === 'gr');
    
    if (value === 0) {
        if (existingItemIndex !== -1) {
            cart.splice(existingItemIndex, 1);
        }
    } else {
        if (existingItemIndex !== -1) {
            cart[existingItemIndex].qty = value; // Update quantity in grams
            cart[existingItemIndex].price = pricePerGram; // Ensure price is per gram
    } else {
        cart.push({
            id: product.id,
            name: product.urun_adi,
                price: pricePerGram, // Price per gram
                qty: value,          // Quantity in grams
                olcumBirimi: 'gr',   // Selling unit is grams
                note: ''
        });
    }
    }
    
    updateCartCount();
    renderCart();
    // toggleCart(); // Optionally open cart
}


// Para birimi formatı için yardımcı fonksiyon
function formatCurrency(value) {
    if (isNaN(parseFloat(value))) return '0,00 ₺';
    return parseFloat(value).toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' ₺';
}

function formatStockDisplay(stok_miktari, olcum_birimi) {
    const value = parseFloat(stok_miktari);
    if (isNaN(value)) return `0 ${olcum_birimi || ''}`;
    
    if (olcum_birimi === 'kg') {
        return `${value.toLocaleString('tr-TR', {minimumFractionDigits:0, maximumFractionDigits:3})} kg (${Math.round(value * 1000).toLocaleString('tr-TR')} gr)`;
    } else if (olcum_birimi === 'gr') {
        return `${Math.round(value).toLocaleString('tr-TR')} gr`;
    } else { // adet etc.
        return `${Math.round(value).toLocaleString('tr-TR')} ${olcum_birimi}`;
    }
}

// Kapat butonuna özel fonksiyon ekleyelim
function closeCustomerModal() {
  const customerModal = document.getElementById('customerModal');
  if (customerModal) {
    customerModal.classList.add('hidden');
    customerModal.classList.remove('show');
    
    // Z-index'i geri al
    customerModal.style.zIndex = '55';
  }
}

// This secondary script block has conflicting logic with the main script.
// Some parts are commented out to prioritize the main script's more detailed functionality.
// If you intend to use search.js and this block's display logic, you'll need to resolve conflicts.
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const customerSearchInput = document.getElementById('customerSearch'); // Renamed to avoid conflict with main script's customerSearch element ID
    
    // Güvenlik kontrolleri
    const productsData = typeof <?= json_encode($urunler, JSON_NUMERIC_CHECK) ?> !== 'undefined' ? 
                         <?= json_encode($urunler, JSON_NUMERIC_CHECK) ?> : []; // Renamed
    const customersData = typeof <?= json_encode($customerRows, JSON_NUMERIC_CHECK) ?> !== 'undefined' ? 
                          <?= json_encode($customerRows, JSON_NUMERIC_CHECK) ?> : []; // Renamed
    
    // Ürün arama fonksiyonu (Potentially conflicts with main script's searchInput listener)
    // searchInput.addEventListener('input', function() {
    //     const searchText = this.value.trim();
    //     // Assuming searchProducts is defined in search.js
    //     const localFilteredProducts = typeof searchProducts === 'function' ? searchProducts(searchText, productsData) : productsData.filter(p => p.urun_adi.toLowerCase().includes(searchText));
    //     displayProductsLocal(localFilteredProducts); // Use a locally named display function
    // });
    
    // Müşteri arama fonksiyonu (Potentially conflicts with main script's customerSearch listener)
    // customerSearchInput.addEventListener('input', function() {
    //     const searchText = this.value.trim();
    //     // Assuming searchCustomers is defined in search.js
    //     const localFilteredCustomers = typeof searchCustomers === 'function' ? searchCustomers(searchText, customersData) : customersData.filter(c => (c.ad + ' ' + c.soyad).toLowerCase().includes(searchText));
    //     displayCustomersLocal(localFilteredCustomers); // Use a locally named display function
    // });
    
    // Ürünleri görüntüleme fonksiyonu (local to this script block)
    function displayProductsLocal(productsToDisplay) {
        const gridContainer = document.getElementById('productGrid');
        if (!gridContainer || !searchInput) return;
        
        const searchText = searchInput.value.trim(); // searchInput from this scope or global?
        
        gridContainer.innerHTML = productsToDisplay.map(product => {
            // Assuming highlightSearchResults is defined in search.js
            const highlightedName = typeof highlightSearchResults === 'function' ? highlightSearchResults(product.urun_adi, searchText) : product.urun_adi;
            return `
            <div class="product-card bg-white p-2 rounded-lg border border-gray-200 hover:border-primary cursor-pointer"
                 onclick="addToCart(${product.id})"> {/* Call main addToCart with ID */}
                <div class="aspect-square bg-gray-100 rounded-lg mb-2 overflow-hidden">
                    <img src="${product.resim_url || 'uploads/products/default.jpg'}" 
                         alt="${product.urun_adi}"
                         class="w-full h-full object-contain"
                         onerror="this.src='uploads/products/default.jpg'">
                </div>
                <div>
                    <h3 class="font-medium text-sm text-gray-900 line-clamp-2">
                        ${highlightedName}
                    </h3>
                    <div class="mt-1 text-xs text-gray-500">
                        Stok: ${formatStockDisplay(product.stok_miktari, product.olcum_birimi)}
                    </div>
                    <div class="mt-2 flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900">
                            ${formatCurrency(product.satis_fiyati)}
                        </span>
                    </div>
                </div>
            </div>
        `}).join('');
    }
    
    // Müşterileri görüntüleme fonksiyonu (local to this script block)
    function displayCustomersLocal(customersToDisplay) {
        const customerList = document.getElementById('customerList'); // Main script also uses this ID
        if (!customerList || !customerSearchInput) return;
        
        const searchText = customerSearchInput.value.trim();
        
        customerList.innerHTML = customersToDisplay.map(customer => {
            const fullName = `${customer.ad} ${customer.soyad || ''}`;
            const highlightedFullName = typeof highlightSearchResults === 'function' ? highlightSearchResults(fullName, searchText) : fullName;
            return `
            <button
                onclick="selectCustomer(${customer.id})" {/* Call main selectCustomer with ID */}
                class="w-full text-left px-3 py-2 hover:bg-gray-100 rounded text-xs sm:text-sm"
            >
                ${highlightedFullName}
                <div class="text-xs text-gray-500">
                    Cari Bakiye: ${formatCurrency(customer.cari_bakiye)}
                </div>
            </button>
        `}).join('');
    }
    
    // İlk yükleme (Commented out to prevent overwriting main script's rendering)
    // if (gridContainer && productsData.length > 0) {
    //     displayProductsLocal(productsData);
    // }
    // if (customerList && customersData.length > 0) {
    //     displayCustomersLocal(customersData);
    // }
});

// ... existing code ...

function renderCustomers(searchTerm=''){
  const cList=document.getElementById('customerList');
  const lst = customersFromDB.filter(m=>{
    if(!searchTerm) return true;
    const fullName=`${m.ad.toLowerCase()} ${(m.soyad||'').toLowerCase()}`;
    return fullName.includes(searchTerm);
  });
  
  if(lst.length===0){
    cList.innerHTML=`<div class="p-4 text-gray-500 text-center">Müşteri bulunamadı</div>`;
    return;
  }
  
  cList.innerHTML = lst.map(m=>`
    <button 
      onclick="selectCustomer(${m.id})"
      class="block w-full text-left p-3 hover:bg-gray-50 rounded-lg border-b border-gray-100"
    >
      <div class="font-medium">${m.ad} ${m.soyad||''}</div>
      <div class="text-sm text-gray-500" id="customerBalance_${m.id}">Bakiye yükleniyor...</div>
    </button>
  `).join('');
  
  // Bakiye bilgisini AJAX ile getir
  lst.forEach(m => {
    fetchCustomerBalance(m.id);
  });
}

// Müşteri bakiyesini AJAX ile getir
function fetchCustomerBalance(customerId) {
  fetch(`ajax_islem.php?islem=musteri_bakiye&id=${customerId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        const balanceDisplay = document.getElementById(`customerBalance_${customerId}`);
        if (balanceDisplay) {
          let bakiyeHtml = `<div>TRY: ${parseFloat(data.try_bakiye).toLocaleString('tr-TR')} ₺</div>`;
          
          // Döviz bakiyelerini göster (yalnızca sıfırdan farklı olanları)
          if (data.usd_bakiye && data.usd_bakiye !== 0) {
            bakiyeHtml += `<div>USD: ${parseFloat(data.usd_bakiye).toLocaleString('tr-TR')} $</div>`;
          }
          if (data.eur_bakiye && data.eur_bakiye !== 0) {
            bakiyeHtml += `<div>EUR: ${parseFloat(data.eur_bakiye).toLocaleString('tr-TR')} €</div>`;
          }
          if (data.gbp_bakiye && data.gbp_bakiye !== 0) {
            bakiyeHtml += `<div>GBP: ${parseFloat(data.gbp_bakiye).toLocaleString('tr-TR')} £</div>`;
          }
          
          balanceDisplay.innerHTML = bakiyeHtml;
        }
      }
    })
    .catch(error => console.error('Error fetching balance:', error));
}

function filterCustomers(){
  const searchTerm=document.getElementById('customerSearch')?.value?.toLowerCase() || '';
  renderCustomers(searchTerm);
}

function renderCustomers(searchTerm=''){
  const cList=document.getElementById('customerList');
  const lst = customersFromDB.filter(m=>{
    if(!searchTerm) return true;
    const fullName=`${m.ad.toLowerCase()} ${(m.soyad||'').toLowerCase()}`;
    return fullName.includes(searchTerm);
  });
  
  if(lst.length===0){
    cList.innerHTML=`<div class="p-4 text-gray-500 text-center">Müşteri bulunamadı</div>`;
    return;
  }
  
  cList.innerHTML = lst.map(m=>`
    <button 
      onclick="selectCustomer(${m.id})"
      class="block w-full text-left p-3 hover:bg-gray-50 rounded-lg border-b border-gray-100"
    >
      <div class="font-medium">${m.ad} ${m.soyad||''}</div>
      <div class="text-sm text-gray-500" id="customerBalance_${m.id}">Bakiye yükleniyor...</div>
    </button>
  `).join('');
  
  // Bakiye bilgisini AJAX ile getir
  lst.forEach(m => {
    fetchCustomerBalance(m.id);
  });
}

// Müşteriler için dropdown fonksiyonları
function filterCustomersDropdown(searchTerm = '') {
  const searchTermLower = convertTurkishToBasic(searchTerm.toLowerCase());
  
  // Dropdown listesini seç
  const dropdownList = document.getElementById('customerDropdownList');
  if (!dropdownList) return;
  
  // Tüm müşterilerden arama terimine uyanları filtrele
  const filteredCustomers = customersFromDB.filter(customer => {
    const fullName = convertTurkishToBasic((customer.ad + ' ' + (customer.soyad || '')).toLowerCase());
    return fullName.includes(searchTermLower);
  });
  
  // Filtrelenmiş müşterileri göster
  if (filteredCustomers.length === 0) {
    dropdownList.innerHTML = '<div class="px-4 py-2 text-sm text-gray-500">Müşteri bulunamadı</div>';
  } else {
    dropdownList.innerHTML = filteredCustomers.map(customer => {
      // TRY bakiyesini formatla
      const tryBakiye = parseFloat(customer.cari_bakiye) || 0;
      let bakiyeHtml = `<div>TRY: ${tryBakiye.toLocaleString('tr-TR')} ₺</div>`;
      
      // Döviz bakiyelerini ekle (varsa)
      if (customer.usd_bakiye && parseFloat(customer.usd_bakiye) !== 0) {
        bakiyeHtml += `<div>USD: ${parseFloat(customer.usd_bakiye).toLocaleString('tr-TR')} $</div>`;
      }
      if (customer.eur_bakiye && parseFloat(customer.eur_bakiye) !== 0) {
        bakiyeHtml += `<div>EUR: ${parseFloat(customer.eur_bakiye).toLocaleString('tr-TR')} €</div>`;
      }
      if (customer.gbp_bakiye && parseFloat(customer.gbp_bakiye) !== 0) {
        bakiyeHtml += `<div>GBP: ${parseFloat(customer.gbp_bakiye).toLocaleString('tr-TR')} £</div>`;
      }
      
      return `
      <div 
        class="px-4 py-2 hover:bg-gray-100 cursor-pointer" 
        onclick="selectCustomerFromDropdown(${customer.id})"
      >
        <div class="text-sm font-medium">${customer.ad} ${customer.soyad || ''}</div>
          <div class="text-xs text-gray-500">${bakiyeHtml}</div>
      </div>
      `;
    }).join('');
  }
  
  // NOT: Bu kısım her keystrokes'da tüm müşteri bakiyelerini çekiyordu ve sonsuz döngüye giriyordu.
  // İlk yüklemede bir kez çekip saklayacağız, her keystroke'ta değil.
  // Böylece arama işleminde performans sorunları olmayacak.
}

function selectCustomerFromDropdown(customerId) {
  // Mevcut selectCustomer fonksiyonunu kullan
  selectCustomer(customerId);
  
  // Dropdown'ı gizle
  document.getElementById('customerDropdown').classList.add('hidden');
  document.getElementById('customerSearchDropdown').value = '';
  
  // Seçilen müşterinin adını arama kutusuna yaz
  const found = customersFromDB.find(x => x.id == customerId);
  if (found) {
    document.getElementById('customerSearchDropdown').value = `${found.ad} ${found.soyad || ''}`;
  }
}

// Event listener'ları eklemek için DOMContentLoaded'a eklemeler
document.addEventListener('DOMContentLoaded', () => {
  // Yeni müşteri arama dropdown için event listener
  const customerSearchDropdown = document.getElementById('customerSearchDropdown');
  if (customerSearchDropdown) {
    customerSearchDropdown.addEventListener('input', function() {
      // Input değeri boş değilse dropdown'ı göster
      document.getElementById('customerDropdown').classList.remove('hidden');
      
      // Arama sonuçlarını filtrele ve göster
      filterCustomersDropdown(this.value.trim());
    });
    
    customerSearchDropdown.addEventListener('focus', function() {
      // Input'a focus olduğunda dropdown'ı göster
      document.getElementById('customerDropdown').classList.remove('hidden');
      
      // İlk açılışta tüm müşterileri göster
      filterCustomersDropdown('');
    });
    
    // Dropdown dışına tıklanınca kapanması için
    document.addEventListener('click', function(e) {
      const dropdown = document.getElementById('customerDropdown');
      if (!dropdown) return;
      
      if (!dropdown.contains(e.target) && e.target !== customerSearchDropdown) {
        dropdown.classList.add('hidden');
      }
    });
  }
});

function clearSelectedCustomer() {
  selectedCustomer = null;
  updateCustomerBalance(); // Gösterimi güncelle
  
  // Görünümü değiştir
  document.getElementById('noCustomerSelected').classList.remove('hidden');
  document.getElementById('customerSelectedInfo').classList.add('hidden');
  
  // Arama kutusunu temizle
  document.getElementById('customerSearchDropdown').value = '';
}
</script>

<!-- Bottom Navigation - Sadece Mobil Görünümde -->
<div class="bottom-nav md:hidden flex items-center justify-around border-t py-2">
  <a href="index.php" class="flex flex-col items-center">
    <i class="ri-home-line text-xl"></i>
    <span class="text-xs mt-1">Ana Sayfa</span>
  </a>
  <a href="satis.php" class="flex flex-col items-center text-primary">
    <i class="ri-shopping-cart-line text-xl"></i>
    <span class="text-xs mt-1">Satış</span>
  </a>
  <a href="urunler.php" class="flex flex-col items-center">
    <i class="ri-box-3-line text-xl"></i>
    <span class="text-xs mt-1">Ürünler</span>
  </a>
  <a href="musteriler.php" class="flex flex-col items-center">
    <i class="ri-team-line text-xl"></i>
    <span class="text-xs mt-1">Müşteriler</span>
  </a>
  <a href="alis.php" class="flex flex-col items-center">
    <i class="ri-store-line text-xl"></i>
    <span class="text-xs mt-1">Alış</span>
  </a>
  <a href="tahsilat.php" class="flex flex-col items-center">
    <i class="ri-money-dollar-circle-line text-xl"></i>
    <span class="text-xs mt-1">Tahsilat</span>
  </a>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Sayfanın en altına eklenecek script -->
<script src="assets/js/search.js"></script>
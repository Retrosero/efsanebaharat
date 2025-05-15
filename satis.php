<?php
// satis.php
require_once 'includes/db.php';
include 'includes/header.php'; // Soldaki menü + top bar layout

// musteriler tablosu (müşteri seçimi)
$customerRows = [];
try {
  // Müşterileri alfabetik sıraya göre getir (ad ve soyad'a göre)
  $stmtC = $pdo->query("SELECT id, ad, soyad, cari_bakiye FROM musteriler ORDER BY ad ASC, soyad ASC");
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
  <h1 class="text-lg sm:text-xl font-semibold mb-3">Satış Ekranı</h1>

  <!-- Yeni Üst Menü Tasarımı -->
  <div class="flex items-center justify-between gap-2 mb-3">
    <!-- Arama Kutusu -->
    <div class="relative flex-1">
      <input
        type="text"
        id="searchInput"
        placeholder="Ürün ara..."
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
  class="modal fixed inset-0 bg-black bg-opacity-50 z-50 hidden"
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
      <!-- Diğer filtre alanları (KOD EKSİLTMEKSİZİN) -->
    </div>
    <div class="mt-4 flex justify-end gap-2">
      <button
        onclick="toggleFilter()"
        class="px-3 py-1.5 text-xs sm:text-sm text-gray-600 hover:bg-gray-100 rounded-button"
      >
        İptal
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
        İsme göre (A-Z)
      </button>
      <button onclick="sortProducts('name-desc')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        İsme göre (Z-A)
      </button>
      <button onclick="sortProducts('price')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        Fiyat (Düşük → Yüksek)
      </button>
      <button onclick="sortProducts('price-desc')" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 rounded text-xs sm:text-sm">
        Fiyat (Yüksek → Düşük)
      </button>
    </div>
  </div>
</div>

<!-- Cart Sidebar -->
<div
  id="cartSidebar"
  class="cart-sidebar fixed top-0 right-0 w-full sm:w-96 h-full bg-white shadow-xl z-50 hidden"
>
  <div class="flex flex-col h-full">
    <div class="p-4 border-b">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-medium">Sepetim</h3>
        <div class="flex items-center gap-2">
          <button onclick="clearCart()" class="p-2 text-red-500 hover:bg-red-50 rounded-full">
            <i class="ri-delete-bin-line"></i>
          </button>
          <button id="closeCart" class="p-2 hover:bg-gray-100 rounded-full">
            <i class="ri-close-line ri-lg"></i>
          </button>
        </div>
      </div>
      <button
        class="w-full flex items-center justify-between p-3 border rounded-lg text-sm"
        onclick="toggleCustomerModal()"
      >
        <span class="text-gray-600" id="selectedCustomerName">Müşteri Seç</span>
        <i class="ri-arrow-down-s-line"></i>
      </button>
    </div>
    <div class="flex-1 overflow-y-auto p-4">
      <div id="cartItems" class="space-y-4">
        <!-- Cart items JS ile -->
      </div>
    </div>
    <div class="p-4 border-t">
      <div class="mb-4">
        <div class="flex items-center justify-between mb-2">
          <span class="text-sm text-gray-600">İskonto Oranı (%)</span>
          <input
            type="number"
            id="discountRate"
            onchange="calculateTotal()"
            class="w-24 px-3 py-1 text-right border rounded text-sm"
            placeholder="0"
          >
        </div>
        <button
          onclick="showOrderNoteModal()"
          class="w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-button"
        >
          <i class="ri-chat-1-line"></i>
          <span id="orderNoteText">Sipariş Notu Ekle</span>
        </button>
      </div>
      <!-- Sipariş Notu Modal -->
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
      <div class="space-y-2 mb-4">
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-600">Ara Toplam</span>
          <span id="subtotal" class="text-gray-600">0,00 ₺</span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-600">İskonto Tutarı</span>
          <span id="discountAmount" class="text-gray-600">0,00 ₺</span>
        </div>
        <div class="flex items-center justify-between">
          <span class="font-medium">Net Toplam</span>
          <span id="total" class="font-medium text-lg">0,00 ₺</span>
        </div>
      </div>
      <button 
        class="w-full py-3 bg-primary text-white rounded-button font-medium"
        onclick="completeOrder()"
      >
        Siparişi Tamamla
      </button>
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
let cart = [];
let selectedCustomer = null;
let searchValue = '';
const currentView = 'grid'; // Her zaman grid görünümü kullanılacak

document.addEventListener('DOMContentLoaded', () => {
  initEvents();
  // İlk yüklemede tüm ürünleri göster
  filteredProducts = [...products];
  renderProducts();
  renderCustomerList();
});

function initEvents(){
  // Arama
  const searchInput = document.getElementById('searchInput');
  
  // Anlık arama için input event listener
  searchInput.addEventListener('input', (e) => {
    searchValue = e.target.value.trim();
    filterProductsBySearch();
    renderProducts();
  });

  // Barkod okuyucu için enter tuşu desteği
  searchInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') {
      searchValue = e.target.value.trim();
      filterProductsBySearch();
      renderProducts();
      searchInput.select(); // Enter'a basınca tüm metni seç
    }
  });
  
  // Sepet kapama
  document.getElementById('closeCart').addEventListener('click',()=>{
    const cs=document.getElementById('cartSidebar');
    cs.classList.add('hidden');
    cs.classList.remove('show');
  });
  
  // Dış click
  document.addEventListener('click',(e)=>{
    const cartSidebar=document.getElementById('cartSidebar');
    const cartBtn=document.getElementById('cartBtn');
    if(!cartSidebar.contains(e.target) && !cartBtn.contains(e.target)){
      cartSidebar.classList.remove('show');
    }
  });
  
  // Müşteri Arama
  document.getElementById('customerSearch').addEventListener('input',(e)=>{
    const val=e.target.value.toLowerCase();
    const filtered=customersFromDB.filter(c=>{
      const fn=(c.ad+' '+(c.soyad||'')).toLowerCase();
      return fn.includes(val);
    });
    renderCustomerList(filtered);
  });
}

// Ürünleri arama terimlerine göre filtrele
function filterProductsBySearch() {
  if (!searchValue) {
    filteredProducts = [...products];
    return;
  }
  
  const searchTerms = searchValue.toLowerCase().split(' ').filter(term => term.length > 0);
  
  filteredProducts = products.filter(product => {
    // Aranacak alanları birleştir
    const searchableText = [
      product.urun_adi || '',
      product.urun_kodu || '',
      product.barkod || '',
      `${product.stok_miktari} ${product.olcum_birimi}`,
      formatCurrency(product.satis_fiyati)
    ].filter(Boolean).join(' ').toLowerCase();
    
    // Tüm arama terimlerini kontrol et
    return searchTerms.every(term => searchableText.includes(term));
  });
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
      <div class="text-sm text-gray-500">Bakiye: ${parseFloat(m.cari_bakiye).toLocaleString('tr-TR')} ₺</div>
    </button>
  `).join('');
}

function toggleCustomerModal(){
  const mod=document.getElementById('customerModal');
  mod.classList.toggle('hidden');
  mod.classList.toggle('show');
}

function selectCustomer(id){
  const found=customersFromDB.find(x=> x.id==id);
  if(!found) return;
  selectedCustomer=found;
  document.getElementById('selectedCustomerName').textContent=`${found.ad} ${found.soyad||''}`;
  toggleCustomerModal();
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
          <img 
            src="${p.resim_url || 'uploads/products/default.jpg'}" 
            alt="${p.urun_adi}"
            class="w-full h-full object-contain"
            onerror="this.src='uploads/products/default.jpg'"
          >
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
                <img src="${url}" class="w-full h-full object-contain" alt="">
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
function addToCart(productId) {
    productId = parseInt(productId);
    const product = products.find(p => p.id === productId);
    if (!product) {
        alert('Ürün bulunamadı!');
    return;
  }
    
    if (product.stok_miktari < 1) {
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
        qtyInputHtml = `
            <div class="flex flex-col space-y-2">
                <div class="flex items-center">
                    <input 
                        type="number" 
                        id="modalQty" 
                        class="w-24 px-3 py-1 border rounded-l text-center" 
                        value="0.1" 
                        min="0.001" 
                        max="${product.stok_miktari}"
                        step="0.001"
                        onchange="validateModalQty()"
                    >
                    <select id="weightUnit" class="border border-l-0 rounded-r px-2 py-1 bg-gray-50" onchange="updateWeightUnit()">
                        <option value="kg" ${product.olcum_birimi === 'kg' ? 'selected' : ''}>kg</option>
                        <option value="gr" ${product.olcum_birimi === 'gr' ? 'selected' : ''}>gr</option>
                    </select>
                </div>
                <div class="text-xs text-gray-500">
                    ${product.olcum_birimi === 'kg' ? 'Stok: ' + product.stok_miktari + ' kg' : 'Stok: ' + product.stok_miktari + ' gr'}
                </div>
            </div>
        `;
        unitLabel = 'Miktar:';
    }
    
    // Modal HTML
    const modalHtml = `
        <div id="qtyModal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50">
            <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md bg-white rounded-lg shadow-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium">${product.urun_adi}</h3>
                    <button onclick="closeQtyModal()" class="p-2 hover:bg-gray-100 rounded-full">
                        <i class="ri-close-line ri-lg"></i>
                    </button>
                </div>
                <div class="mb-4">
                    <p class="text-sm text-gray-600 mb-2">Mevcut Stok: ${product.stok_miktari} ${product.olcum_birimi}</p>
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
    } else {
        // Sayısal değeri al (binlik ayırıcıları kaldırarak)
        let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        if (value < 0.001) value = 0.001;
        if (value > product.stok_miktari) value = product.stok_miktari;
        input.value = value;
    }
}

function updateWeightUnit() {
    const weightUnit = document.getElementById('weightUnit').value;
    const input = document.getElementById('modalQty');
    const product = products.find(p => p.id === selectedProductId);
    
    // Sayısal değeri al (binlik ayırıcıları kaldırarak)
    let currentValue = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
    
    // Eğer birim değişirse, değeri dönüştür
    if (weightUnit === 'kg' && product.olcum_birimi === 'gr') {
        // gr -> kg (1000 gr = 1 kg)
        input.value = (currentValue / 1000).toFixed(3);
        input.step = "0.001";
        input.min = "0.001";
        input.max = (product.stok_miktari / 1000).toFixed(3);
    } else if (weightUnit === 'gr' && product.olcum_birimi === 'kg') {
        // kg -> gr (1 kg = 1000 gr)
        input.value = (currentValue * 1000).toFixed(0);
        input.step = "1";
        input.min = "1";
        input.max = (product.stok_miktari * 1000).toFixed(0);
    }
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
        // Sayısal değeri al (binlik ayırıcıları kaldırarak)
        let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        let step = product.olcum_birimi === 'kg' ? 0.1 : 100;
        if (value > step) {
            input.value = (value - step).toFixed(product.olcum_birimi === 'kg' ? 3 : 0);
        }
    }
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
        // Sayısal değeri al (binlik ayırıcıları kaldırarak)
        let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
        let step = product.olcum_birimi === 'kg' ? 0.1 : 100;
        if (value + step <= product.stok_miktari) {
            input.value = (value + step).toFixed(product.olcum_birimi === 'kg' ? 3 : 0);
        }
    }
}

function confirmAddToCart() {
    const product = products.find(p => p.id === selectedProductId);
    if (!product) {
        alert('Ürün bulunamadı!');
        return;
    }
    
    const qtyInput = document.getElementById('modalQty');
    let qty = 0;
    let olcumBirimi = product.olcum_birimi;
    
    if (product.olcum_birimi === 'adet') {
        qty = parseInt(qtyInput.value) || 1;
    } else {
        // Sayısal değeri al (binlik ayırıcıları kaldırarak)
        qty = parseFloat(qtyInput.value.replace(/\./g, '').replace(',', '.')) || 0.1;
        // Eğer ağırlık birimi seçildiyse, seçilen birimi al
        if (document.getElementById('weightUnit')) {
            olcumBirimi = document.getElementById('weightUnit').value;
        }
    }
    
    // Stok kontrolü
    if (qty > product.stok_miktari) {
        alert('Yetersiz stok!');
        qtyInput.value = product.stok_miktari;
        return;
    }
    
    // Sepette var mı kontrol et
    const existingItem = cart.find(item => item.id === selectedProductId);
    if (existingItem) {
        existingItem.qty = qty;
        existingItem.olcumBirimi = olcumBirimi;
    } else {
        cart.push({
            id: product.id,
            name: product.urun_adi,
            price: parseFloat(product.satis_fiyati),
            qty: qty,
            olcumBirimi: olcumBirimi
        });
    }
    
    // Sepeti güncelle
    updateCartCount();
    renderCart();
    
    // Sepeti göster
    toggleCart();
    
    // Modal'ı kapat
    closeQtyModal();
}

function removeFromCart(productId) {
    const index = cart.findIndex(item => item.id === productId);
    if (index === -1) return;
    
    cart.splice(index, 1);
    updateCartCount();
    renderCart();
}
function clearCart(){
  if(confirm("Sepeti temizlemek istiyor musunuz?")){
    cart=[];
    updateCartCount();
    renderCart();
  }
}
function updateCartCount(){
  const uniqueItems = cart.length; // Benzersiz kalem sayısı
  document.getElementById('cartCount').textContent = uniqueItems;
}
function renderCart(){
  const cItems=document.getElementById('cartItems');
  cItems.innerHTML= cart.map(i=>`
    <div class="flex gap-4 p-4 bg-gray-50 rounded-lg">
      <div class="flex-1">
        <h4 class="font-medium">${i.name}</h4>
        <p class="text-sm text-gray-500">Kod: PRD${String(i.id).padStart(4, '0')}</p>
        ${
          i.note
            ? `<button class="text-sm text-blue-500 mt-1" onclick="openItemNoteModal(${i.id})">${i.note}</button>`
            : `<button class="text-sm text-blue-500 mt-1" onclick="openItemNoteModal(${i.id})">Not Düzenle</button>`
        }
      </div>
      <div class="flex flex-col items-end justify-between">
        <div class="flex items-center">
          <button class="px-2 py-1 bg-gray-200 rounded-l" onclick="decreaseQty(${i.id})">-</button>
          <input 
            type="number"
            class="w-16 text-center border-t border-b"
            value="${i.qty}"
            onchange="updateQty(${i.id}, this.value)"
            ${i.olcumBirimi !== 'adet' ? 'step="0.001" min="0.001"' : 'min="1"'}
          >
          ${i.olcumBirimi !== 'adet' ? 
            `<span class="px-2 py-1 border-t border-b bg-gray-50">${i.olcumBirimi}</span>` : 
            ''}
          <button class="px-2 py-1 bg-gray-200 rounded-r" onclick="increaseQty(${i.id})">+</button>
        </div>
        <button class="p-1 text-red-500 hover:bg-red-50 rounded" onclick="removeFromCart(${i.id})">
          <i class="ri-delete-bin-line"></i>
        </button>
      </div>
    </div>
  `).join('') || '<div class="text-center py-8 text-gray-500">Sepetinizde ürün bulunmuyor.</div>';
  
  // Toplam hesapla
  const subtotal = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
  const discountAmount = 0; // İndirim tutarı (şimdilik 0)
  const total = subtotal - discountAmount;
  
  document.getElementById('subtotal').textContent = formatCurrency(subtotal);
  document.getElementById('discountAmount').textContent = formatCurrency(discountAmount);
  document.getElementById('total').textContent = formatCurrency(total);
}
function updateQty(id, value) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  
  const product = products.find(p => p.id === id);
  if (!product) return;
  
  if (item.olcumBirimi === 'adet') {
    let qty = parseInt(value) || 1;
    if (qty < 1) qty = 1;
    if (qty > product.stok_miktari) qty = product.stok_miktari;
    item.qty = qty;
  } else {
    let qty = parseFloat(value) || 0.001;
    if (qty < 0.001) qty = 0.001;
    if (qty > product.stok_miktari) qty = product.stok_miktari;
    item.qty = qty;
  }
  
  renderCart();
}
function increaseQty(id) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  
  const product = products.find(p => p.id === id);
  if (!product) return;
  
  if (item.olcumBirimi === 'adet') {
    if (item.qty < product.stok_miktari) {
      item.qty++;
    }
  } else {
    let step = item.olcumBirimi === 'kg' ? 0.1 : 100;
    if (item.qty + step <= product.stok_miktari) {
      item.qty = parseFloat((item.qty + step).toFixed(item.olcumBirimi === 'kg' ? 3 : 0));
    }
  }
  
  renderCart();
}
function decreaseQty(id) {
  const item = cart.find(i => i.id === id);
  if (!item) return;
  
  const product = products.find(p => p.id === id);
  
  if (item.olcumBirimi === 'adet') {
    if (item.qty > 1) {
      item.qty--;
    }
  } else {
    let step = item.olcumBirimi === 'kg' ? 0.1 : 100;
    if (item.qty > step) {
      item.qty = parseFloat((item.qty - step).toFixed(item.olcumBirimi === 'kg' ? 3 : 0));
    }
  }
  
  renderCart();
}
function calculateTotal(){
  let subtotal=0;
  cart.forEach(i=> subtotal+=(i.price*i.qty));
  const dr=parseFloat(document.getElementById('discountRate').value)||0;
  const discount= subtotal*(dr/100);
  const total= subtotal-discount;
  document.getElementById('subtotal').textContent= subtotal.toLocaleString('tr-TR')+' ₺';
  document.getElementById('discountAmount').textContent= discount.toLocaleString('tr-TR')+' ₺';
  document.getElementById('total').textContent= total.toLocaleString('tr-TR')+' ₺';
}

// Filtre
function toggleFilter(){
  const fm=document.getElementById('filterModal');
  fm.classList.toggle('hidden');
  fm.classList.toggle('show');
}
function applyFilter(){
  const minP=parseFloat(document.getElementById('minPrice').value)||0;
  const maxP=parseFloat(document.getElementById('maxPrice').value)||999999999;
  products=products.filter(x=> x.satis_fiyati>=minP && x.satis_fiyati<=maxP);
  renderProducts();
  toggleFilter();
}

// Sıralama
function toggleSort(){
  const sm=document.getElementById('sortModal');
  sm.classList.toggle('hidden');
  sm.classList.toggle('show');
}
function sortProducts(type){
  switch(type){
    case 'name':
      products.sort((a,b)=> a.urun_adi.localeCompare(b.urun_adi));
      break;
    case 'name-desc':
      products.sort((a,b)=> b.urun_adi.localeCompare(a.urun_adi));
      break;
    case 'price':
      products.sort((a,b)=> a.satis_fiyati-b.satis_fiyati);
      break;
    case 'price-desc':
      products.sort((a,b)=> b.satis_fiyati-a.satis_fiyati);
      break;
  }
  renderProducts();
  toggleSort();
}

// Görünüm değiştirme
// Bu fonksiyon artık kullanılmıyor - sadece grid görünümü kullanıyoruz

// Sayfa yüklendiğinde tercih edilen görünümü uygula
document.addEventListener('DOMContentLoaded', function() {
    // Grid görünümü her zaman aktif
});

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
  const val=document.getElementById('orderNote').value;
  document.getElementById('orderNoteText').textContent= val||'Sipariş Notu Ekle';
  toggleOrderNoteModal();
}

// Ürün Notu
let currentItemNoteId=null;
function openItemNoteModal(id){
  currentItemNoteId=id;
  const item= cart.find(x=> x.id===id);
  document.getElementById('itemNote').value= item && item.note ? item.note : '';
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
  let it= cart.find(x=> x.id===currentItemNoteId);
  if(it) it.note=val;
  toggleItemNoteModal();
  renderCart();
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
    // r.ok kontrolü
    if(!r.ok){
      const errorText = await r.text();
      console.error('HTTP Hatası:', r.status, errorText);
      throw new Error('HTTP Hatası: ' + r.status + ' - ' + errorText);
    }
    // JSON parse
    try {
      return await r.json();
    } catch (e) {
      console.error('JSON parse hatası:', e);
      const responseText = await r.text();
      console.error('Ham yanıt:', responseText);
      throw new Error('JSON parse hatası: ' + e.message);
    }
  })
  .then(resp=>{
    console.log('Sunucu yanıtı:', resp);
    if(resp.success){
      alert("Sipariş başarıyla kaydedildi! Fatura No: " + resp.fatura_id);
      cart=[];
      updateCartCount();
      renderCart();
      document.getElementById('orderNote').value='';
      document.getElementById('orderNoteText').textContent='Sipariş Notu Ekle';
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

// Direkt sepete ekleme fonksiyonları
function validateAndAddToCart(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    
    // Ölçüm birimi kontrolü
    if (product.olcum_birimi !== 'adet') {
        // Eğer ürün kg veya gr ise, ağırlık fonksiyonlarını kullan
        validateAndAddToCartWeight(productId);
        return;
    }
    
    let value = parseInt(input.value) || 0;
    if (value < 0) value = 0;
    if (value > product.stok_miktari) value = product.stok_miktari;
    
    input.value = value;

    // Sepette var mı kontrol et
    const existingItem = cart.find(item => item.id === productId);
    
    if (value === 0) {
        // Sepetten kaldır
        if (existingItem) {
            removeFromCart(productId);
        }
        return;
    }
    
    // Stok kontrolü
    if (value > product.stok_miktari) {
        alert('Yetersiz stok!');
        input.value = product.stok_miktari;
        value = product.stok_miktari;
    }
    
    if (existingItem) {
        existingItem.qty = value; // Miktarı güncelle
    } else {
        cart.push({
            id: product.id,
            name: product.urun_adi,
            price: parseFloat(product.satis_fiyati),
            qty: value,
            olcumBirimi: 'adet'
        });
    }
    
    // Sepeti güncelle
    updateCartCount();
    renderCart();
}

function decreaseDirectQty(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    
    // Ölçüm birimi kontrolü
    if (product.olcum_birimi !== 'adet') {
        // Eğer ürün kg veya gr ise, farklı işlem yapma
        return;
    }
    
    let value = parseInt(input.value) || 0;
    if (value > 0) {
        input.value = value - 1;
        validateAndAddToCart(productId);
    }
}

function increaseDirectQty(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    
    // Ölçüm birimi kontrolü
    if (product.olcum_birimi !== 'adet') {
        // Eğer ürün kg veya gr ise, farklı işlem yapma
        return;
    }
    
    let value = parseInt(input.value) || 0;
    
    if (value < product.stok_miktari) {
        input.value = value + 1;
        validateAndAddToCart(productId);
    }
}

function directAddToCart(productId) {
    productId = parseInt(productId);
    const product = products.find(p => p.id === productId);
    if (!product) {
        alert('Ürün bulunamadı!');
        return;
    }
    
    if (product.stok_miktari < 1) {
        alert('Bu ürün stokta yok!');
        return;
    }
    
    const qtyInput = document.getElementById(`directQty_${productId}`);
    const qty = parseInt(qtyInput.value) || 0;
    
    if (qty === 0) {
        removeFromCart(productId);
        return;
    }
    
    // Stok kontrolü
    if (qty > product.stok_miktari) {
        alert('Yetersiz stok!');
        qtyInput.value = product.stok_miktari;
        return;
    }
    
    // Sepette var mı kontrol et
    const existingItem = cart.find(item => item.id === productId);
    if (existingItem) {
        existingItem.qty = qty; // Miktarı güncelle
    } else {
        cart.push({
            id: product.id,
            name: product.urun_adi,
            price: parseFloat(product.satis_fiyati),
            qty: qty
        });
    }
    
    updateCartCount();
    renderCart();
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
    
    // Tüm resimleri bir diziye topla
    const images = [
        product.resim_url,
        product.resim_url_2,
        product.resim_url_3,
        product.resim_url_4,
        product.resim_url_5,
        product.resim_url_6,
        product.resim_url_7,
        product.resim_url_8,
        product.resim_url_9,
        product.resim_url_10
    ].filter(url => url);
    
    if (images.length === 0) return;

    // Ana resmi ayarla
    const viewerImage = document.getElementById('viewerImage');
    viewerImage.src = images[imageIndex];
    viewerImage.alt = product.urun_adi;

    // Küçük resimleri oluştur
    const thumbContainer = document.getElementById('thumbContainer');
    thumbContainer.innerHTML = images.map((url, idx) => `
        <div class="w-16 h-16 bg-white/10 rounded cursor-pointer ${idx === imageIndex ? 'ring-2 ring-white' : ''}"
             onclick="changeImage(${idx})">
            <img src="${url}" 
                 alt="${product.urun_adi} - ${idx + 1}" 
                 class="w-full h-full object-contain rounded"
                 onerror="this.src='resimyok.jpg'">
        </div>
    `).join('');

    // Navigasyon butonlarını ayarla
    const prevButton = document.getElementById('prevImage');
    const nextButton = document.getElementById('nextImage');
    
    if (prevButton && nextButton) {
        prevButton.style.display = imageIndex > 0 ? 'block' : 'none';
        nextButton.style.display = imageIndex < images.length - 1 ? 'block' : 'none';
        
        prevButton.onclick = () => changeImage(imageIndex - 1);
        nextButton.onclick = () => changeImage(imageIndex + 1);
    }

    // Modal'ı göster
    const modal = document.getElementById('imageViewerModal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }

    // Klavye kontrollerini ekle
    document.addEventListener('keydown', handleKeyboardNavigation);
}

function changeImage(newIndex) {
    if (!currentProduct) return;
    
    const images = [
        currentProduct.resim_url,
        currentProduct.resim_url_2,
        currentProduct.resim_url_3,
        currentProduct.resim_url_4,
        currentProduct.resim_url_5,
        currentProduct.resim_url_6,
        currentProduct.resim_url_7,
        currentProduct.resim_url_8,
        currentProduct.resim_url_9,
        currentProduct.resim_url_10
    ].filter(url => url);
    
    if (newIndex < 0 || newIndex >= images.length) return;
    
    currentImageIndex = newIndex;
    
    // Ana resmi güncelle
    const viewerImage = document.getElementById('viewerImage');
    viewerImage.src = images[newIndex];

    // Küçük resimleri güncelle
    const thumbs = document.querySelectorAll('#thumbContainer > div');
    thumbs.forEach((thumb, idx) => {
        if (idx === newIndex) {
            thumb.classList.add('ring-2', 'ring-white');
        } else {
            thumb.classList.remove('ring-2', 'ring-white');
        }
    });
    
    // Navigasyon butonlarını güncelle
    const prevButton = document.getElementById('prevImage');
    const nextButton = document.getElementById('nextImage');
    prevButton.style.display = newIndex > 0 ? 'block' : 'none';
    nextButton.style.display = newIndex < images.length - 1 ? 'block' : 'none';
}

function handleKeyboardNavigation(e) {
    if (document.getElementById('imageViewerModal').classList.contains('hidden')) return;

    switch (e.key) {
        case 'ArrowLeft':
            changeImage(currentImageIndex - 1);
            break;
        case 'ArrowRight':
            changeImage(currentImageIndex + 1);
            break;
        case 'Escape':
            closeImageViewer();
            break;
    }
}

function closeImageViewer() {
    const modal = document.getElementById('imageViewerModal');
    modal.classList.add('hidden');
    document.removeEventListener('keydown', handleKeyboardNavigation);
    currentProduct = null;
    currentImageIndex = 0;
}

// JavaScript: Hamburger Menü Toggle
const menuToggleBtn = document.getElementById('menuToggleBtn');
const expandedMenu = document.getElementById('expandedMenu');

if (menuToggleBtn && expandedMenu) {
  menuToggleBtn.addEventListener('click', () => {
    expandedMenu.classList.toggle('hidden');
    
    // Icon değiştir
    const icon = menuToggleBtn.querySelector('i');
    if (expandedMenu.classList.contains('hidden')) {
      icon.classList.remove('ri-close-line');
      icon.classList.add('ri-menu-line');
    } else {
      icon.classList.remove('ri-menu-line');
      icon.classList.add('ri-close-line');
    }
  });
  
  // Sayfa dışına tıklandığında menüyü kapat
  document.addEventListener('click', (e) => {
    if (!menuToggleBtn.contains(e.target) && !expandedMenu.contains(e.target)) {
      expandedMenu.classList.add('hidden');
      const icon = menuToggleBtn.querySelector('i');
      icon.classList.remove('ri-close-line');
      icon.classList.add('ri-menu-line');
    }
  });
}

// Ağırlık birimi değiştirme fonksiyonu (direkt ürün kartı için)
function updateWeightUnitDirect(productId) {
    const product = products.find(p => p.id === productId);
    if (!product) return;
    
    const input = document.getElementById(`directQty_${productId}`);
    const weightUnit = document.getElementById(`directUnit_${productId}`).value;
    
    // Sayısal değeri al (binlik ayırıcıları kaldırarak)
    let currentValue = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
    
    // Eğer birim değişirse, değeri dönüştür
    if (weightUnit === 'kg' && product.olcum_birimi === 'gr') {
        // gr -> kg (1000 gr = 1 kg)
        input.value = (currentValue / 1000).toFixed(3);
        input.step = "0.001";
        input.min = "0.001";
        input.max = (product.stok_miktari / 1000).toFixed(3);
    } else if (weightUnit === 'gr' && product.olcum_birimi === 'kg') {
        // kg -> gr (1 kg = 1000 gr)
        input.value = (currentValue * 1000).toFixed(0);
        input.step = "1";
        input.min = "1";
        input.max = (product.stok_miktari * 1000).toFixed(0);
    }
    
    // Değeri formatla
    validateAndAddToCartWeight(productId);
}

// Ağırlık ürünleri için sepete ekleme fonksiyonu
function validateAndAddToCartWeight(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const product = products.find(p => p.id === productId);
    
    // Sayısal değeri al (hem nokta hem virgül için destek)
    let inputValue = input.value.replace(/\./g, '').replace(',', '.');
    let value = parseFloat(inputValue) || 0;
    
    if (value < 1) value = 1;
    
    // Birim kontrolü ve dönüşümü
    let olcumBirimi = 'gr'; // Varsayılan olarak gram
    let convertedQty = value;
    
    // Stok kontrolü
    let maxStock = product.olcum_birimi === 'kg' ? 
        parseFloat(product.stok_miktari) * 1000 : 
        parseFloat(product.stok_miktari);
    
    if (value > maxStock) {
        alert('Yetersiz stok!');
        input.value = maxStock;
        value = maxStock;
        return;
    }
    
    // Değeri formatla
    input.value = value;
    
    // Fiyat hesaplama
    let itemPrice;
    
    if (product.olcum_birimi === 'kg') {
        // Ürün kg bazlı ise, gram fiyatını hesapla
        itemPrice = parseFloat(product.satis_fiyati) / 1000; // kg fiyatı / 1000 = gram fiyatı
        olcumBirimi = 'gr';
    } else {
        // Ürün zaten gram bazlı ise
        itemPrice = parseFloat(product.satis_fiyati);
        olcumBirimi = 'gr';
    }
    
    // Sepette var mı kontrol et
    const existingItem = cart.find(item => item.id === productId);
    
    if (value === 0) {
        if (existingItem) {
            removeFromCart(productId);
        }
        return;
    }
    
    if (existingItem) {
        existingItem.qty = value;
        existingItem.olcumBirimi = olcumBirimi;
        existingItem.price = itemPrice;
    } else {
        cart.push({
            id: product.id,
            name: product.urun_adi,
            price: itemPrice,
            qty: value,
            olcumBirimi: olcumBirimi
        });
    }
    
    console.log(`Ürün: ${product.urun_adi}, Miktar: ${value} ${olcumBirimi}, Birim Fiyat: ${itemPrice.toFixed(6)} ₺/${olcumBirimi}`);
    
    // Sepeti güncelle
    updateCartCount();
    renderCart();
    toggleCart();
}

// Ağırlık ürünleri için sepete ekleme butonu
function addToCartWeight(productId) {
    const input = document.getElementById(`directQty_${productId}`);
    const weightUnit = document.getElementById(`directUnit_${productId}`).value || 'gr';
    const product = products.find(p => p.id === productId);
    
    // Sayısal değeri al (binlik ayırıcıları kaldırarak)
    let value = parseFloat(input.value.replace(/\./g, '').replace(',', '.')) || 0;
    if (value <= 0) return;
    
    // Birim dönüşümü yap
    let convertedValue = value;
    let salesUnit = weightUnit; // Satış birimi
    
    // Stok kontrolü
    let maxStock;
    
    if (product.olcum_birimi === 'kg') {
        maxStock = parseFloat(product.stok_miktari) * 1000; // kg -> gr
        if (weightUnit === 'kg') {
            convertedValue = value * 1000; // kg -> gr
            maxStock = maxStock / 1000; // gr -> kg için karşılaştırma
        }
    } else {
        maxStock = parseFloat(product.stok_miktari);
        if (weightUnit === 'kg') {
            convertedValue = value * 1000; // kg -> gr
            maxStock = maxStock / 1000; // gr -> kg için karşılaştırma
        }
    }
    
    if (value > maxStock) {
        alert('Yetersiz stok!');
        input.value = maxStock;
        return;
    }
    
    // Fiyat hesaplama
    let itemPrice = parseFloat(product.satis_fiyati);
    
    // Eğer ürün kg cinsinden ve gr olarak satılıyorsa veya tam tersi
    if (weightUnit === 'gr' && product.olcum_birimi === 'kg') {
        // Gram fiyatını hesapla (kg fiyatı / 1000)
        itemPrice = itemPrice / 1000;
    } else if (weightUnit === 'kg' && product.olcum_birimi === 'gr') {
        // Kg fiyatını hesapla (gr fiyatı * 1000)
        itemPrice = itemPrice * 1000;
    }
    
    // Sepette var mı kontrol et
    const existingItem = cart.find(item => item.id === productId);
    if (existingItem) {
        existingItem.qty = value;
        existingItem.olcumBirimi = weightUnit;
        existingItem.price = itemPrice;
    } else {
        cart.push({
            id: product.id,
            name: product.urun_adi,
            price: itemPrice,
            qty: value,
            olcumBirimi: weightUnit
        });
    }
    
    console.log(`Ürün: ${product.urun_adi}, Miktar: ${value} ${weightUnit}, Birim Fiyat: ${itemPrice.toFixed(6)} ₺/${weightUnit}`);
    
    // Sepeti güncelle
    updateCartCount();
    renderCart();
    
    // Sepeti göster
    toggleCart();
}

function filterProducts(searchValue = '') {
    if (!searchValue) return products;
    
    const searchTerms = searchValue.toLowerCase().split(' ').filter(term => term.length > 0);
    
    return products.filter(product => {
        // Aranacak alanları birleştir
        const searchableText = [
            product.urun_adi,
            product.urun_kodu,
            product.barkod,
            `${product.stok_miktari} ${product.olcum_birimi}`,
            formatCurrency(product.satis_fiyati)
        ].filter(Boolean).join(' ').toLowerCase();
        
        // Tüm arama terimlerini kontrol et
        return searchTerms.every(term => searchableText.includes(term));
    });
}

// Para birimi formatı için yardımcı fonksiyon
function formatCurrency(value) {
    return parseFloat(value).toLocaleString('tr-TR', {minimumFractionDigits: 2}) + ' ₺';
}

function formatStockDisplay(stok_miktari, olcum_birimi, stok_kg) {
    // Stok_kg değeri varsa bunu kullanalım, yoksa geleneksel yöntemle devam edelim
    if (stok_kg !== undefined && stok_kg !== null) {
        if (olcum_birimi === 'kg' || olcum_birimi === 'gr') {
            // Kilogram değerini göster (ve gram olarak göster)
            const kg_value = parseFloat(stok_kg);
            return `${kg_value} kg (${Math.round(kg_value * 1000)} gr)`;
        }
    }
    
    // Stok_kg değeri yoksa veya olcum_birimi adet ise geleneksel yöntemi kullan
    const value = parseFloat(stok_miktari);
    
    if (olcum_birimi === 'kg') {
        return `${value} kg (${Math.round(value * 1000)} gr)`;
    } else if (olcum_birimi === 'gr') {
        return `${value} gr`;
    } else {
        return `${value} ${olcum_birimi}`;
    }
}
</script>

<?php include 'includes/footer.php'; ?>

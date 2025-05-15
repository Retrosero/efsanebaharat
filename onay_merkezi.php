<?php
// onay_merkezi.php
require_once 'includes/db.php';
include 'includes/header.php';
?>

<div class="flex h-screen">
  <div class="w-64 bg-white shadow-lg">
    <div class="p-4 border-b">
      <h1 class="text-xl font-bold text-gray-800">Onay Merkezi</h1>
    </div>
    <nav class="p-4">
      <div id="menu-items">
        <!-- Menu items JS ile doldurulacak -->
      </div>
    </nav>
  </div>
  <div class="flex-1 overflow-hidden">
    <div class="p-8">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 id="content-title" class="text-2xl font-bold text-gray-800">Sipariş Onay</h2>
          <p class="text-gray-600 mt-1">Onay bekleyen işlemleri görüntüleyin ve yönetin</p>
        </div>
        <div class="flex items-center gap-4">
          <div class="relative">
            <input type="text" placeholder="Ara..." class="pl-10 pr-4 py-2 border rounded-button focus:outline-none focus:border-primary">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
          </div>
        </div>
      </div>
      <div class="bg-white rounded-lg shadow-sm">
        <div class="border-b px-6">
          <div class="flex gap-8">
            <button class="tab-active py-4 px-1 font-medium" data-tab="pending">Bekleyen</button>
            <button class="text-gray-600 py-4 px-1 font-medium" data-tab="approved">Onaylanan</button>
            <button class="text-gray-600 py-4 px-1 font-medium" data-tab="rejected">Reddedilen</button>
          </div>
        </div>
        <div id="content-area" class="p-6">
          <!-- JS ile doldurulacak -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Detay vs. -->
<div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg w-[800px] max-h-[90vh] overflow-y-auto">
    <div class="border-b p-4 flex items-center justify-between">
      <h3 class="text-lg font-medium">İşlem Detayı</h3>
      <button onclick="closeModal('detailsModal')" class="text-gray-400 hover:text-gray-600">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
    <div class="p-6" id="detailContent">
      <!-- Detay içeriği dynamic -->
    </div>
  </div>
</div>

<script>
// Tümü senin orijinal Onay İşlemleri .html'deki JS
// DB bağlantısına göre, mock veriler yerine tabloya dayalı sorgular yapabilirsin.
</script>

<?php include 'includes/footer.php'; ?>

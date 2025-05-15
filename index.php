<?php 
// index.php
require_once 'config.php';
require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';

?>

<!-- Hata Mesajları -->
<?php if (isset($_SESSION['hata_mesaji'])): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4 mx-6 mt-4" role="alert">
  <span class="block sm:inline"><?php echo htmlspecialchars($_SESSION['hata_mesaji']); ?></span>
  <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
    <svg onclick="this.parentElement.parentElement.style.display='none'" class="fill-current h-6 w-6 text-red-500" role="button" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
      <title>Kapat</title>
      <path d="M14.348 14.849a1.2 1.2 0 0 1-1.697 0L10 11.819l-2.651 3.029a1.2 1.2 0 1 1-1.697-1.697l2.758-3.15-2.759-3.152a1.2 1.2 0 1 1 1.697-1.697L10 8.183l2.651-3.031a1.2 1.2 0 1 1 1.697 1.697l-2.758 3.152 2.758 3.15a1.2 1.2 0 0 1 0 1.698z"/>
    </svg>
  </span>
</div>
<?php 
  // Mesajı gösterdikten sonra session'dan kaldır
  unset($_SESSION['hata_mesaji']);
endif; 
?>

<!-- Dashboard İçeriği -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
  <!-- Küçük Kartlar -->
  <div class="bg-white rounded-lg p-6 shadow-sm">
    <div class="text-gray-500 mb-2">Toplam Gelir</div>
    <div class="text-2xl font-semibold">₺458.623,45</div>
    <div class="flex items-center mt-2 text-sm">
      <i class="ri-arrow-up-line text-green-500"></i>
      <span class="text-green-500">12.5%</span>
      <span class="text-gray-500 ml-1">geçen aya göre</span>
    </div>
  </div>
  <div class="bg-white rounded-lg p-6 shadow-sm">
    <div class="text-gray-500 mb-2">Toplam Gider</div>
    <div class="text-2xl font-semibold">₺245.891,23</div>
    <div class="flex items-center mt-2 text-sm">
      <i class="ri-arrow-down-line text-red-500"></i>
      <span class="text-red-500">8.3%</span>
      <span class="text-gray-500 ml-1">geçen aya göre</span>
    </div>
  </div>
  <div class="bg-white rounded-lg p-6 shadow-sm">
    <div class="text-gray-500 mb-2">Kasa Durumu</div>
    <div class="text-2xl font-semibold">₺212.732,22</div>
    <div class="flex items-center mt-2 text-sm">
      <i class="ri-arrow-up-line text-green-500"></i>
      <span class="text-green-500">5.2%</span>
      <span class="text-gray-500 ml-1">geçen aya göre</span>
    </div>
  </div>
  <div class="bg-white rounded-lg p-6 shadow-sm">
    <div class="text-gray-500 mb-2">Vadesi Yaklaşan</div>
    <div class="text-2xl font-semibold">₺89.456,78</div>
    <div class="flex items-center mt-2 text-sm">
      <i class="ri-time-line text-orange-500"></i>
      <span class="text-orange-500">5 işlem</span>
      <span class="text-gray-500 ml-1">önümüzdeki hafta</span>
    </div>
  </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
  <div class="bg-white rounded-lg p-6 shadow-sm">
    <div class="flex items-center justify-between mb-6">
      <h3 class="font-semibold">Gelir-Gider Grafiği</h3>
      <select class="bg-gray-100 rounded px-3 py-1 text-sm">
        <option>Son 6 Ay</option>
        <option>Son 1 Yıl</option>
      </select>
    </div>
    <div id="incomeExpenseChart" class="chart-container" style="min-height: 300px;"></div>
  </div>
  <div class="bg-white rounded-lg p-6 shadow-sm">
    <div class="flex items-center justify-between mb-6">
      <h3 class="font-semibold">Cari Hesap Dağılımı</h3>
      <select class="bg-gray-100 rounded px-3 py-1 text-sm">
        <option>Tümü</option>
        <option>Borçlular</option>
        <option>Alacaklılar</option>
      </select>
    </div>
    <div id="accountsPieChart" class="chart-container" style="min-height: 300px;"></div>
  </div>
</div>

<!-- vs... buraya ana sayfa kısımları devam -->
<script>
// ECharts örnekleri
const incomeExpenseChart = echarts.init(document.getElementById('incomeExpenseChart'));
const accountsPieChart = echarts.init(document.getElementById('accountsPieChart'));

const incomeExpenseOption = {
  // ...
};
const accountsPieOption = {
  // ...
};

incomeExpenseChart.setOption(incomeExpenseOption);
accountsPieChart.setOption(accountsPieOption);

window.addEventListener('resize', () => {
  incomeExpenseChart.resize();
  accountsPieChart.resize();
});
</script>

<?php include 'includes/footer.php'; ?>

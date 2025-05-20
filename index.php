<?php 
// index.php
require_once 'config.php';
require_once 'includes/db.php';

// Kullanıcı adı görüntüleme sorununu doğrudan çözme
if (isset($_SESSION['kullanici_id'])) {
    try {
        $user_stmt = $pdo->prepare("SELECT kullanici_adi FROM kullanicilar WHERE id = :id AND aktif = 1");
        $user_stmt->execute([':id' => $_SESSION['kullanici_id']]);
        $user_name = $user_stmt->fetchColumn();
        
        if ($user_name) {
            $_SESSION['kullanici_adi'] = $user_name;
        }
    } catch (Exception $e) {
        // Hata durumunda hiçbir şey yapma, mevcut session değerini kullan
    }
}

// auth.php dosyası header.php içinde zaten dahil ediliyor
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

<!-- Menü Grid Düzeni -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4 p-4">
    <!-- Satış -->
    <?php if (sayfaErisimKontrol($pdo, 'satis.php')): ?>
    <a href="satis.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-blue-100">
                <i class="ri-shopping-cart-line text-xl text-blue-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Satış</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Alış -->
    <?php if (sayfaErisimKontrol($pdo, 'alis.php')): ?>
    <a href="alis.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-green-100">
                <i class="ri-store-line text-xl text-green-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Alış</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Alış Faturaları -->
    <?php if (sayfaErisimKontrol($pdo, 'alis_faturalari.php')): ?>
    <a href="alis_faturalari.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-purple-100">
                <i class="ri-file-list-line text-xl text-purple-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Alış Faturaları</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Tahsilat -->
    <?php if (sayfaErisimKontrol($pdo, 'tahsilat.php')): ?>
    <a href="tahsilat.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-yellow-100">
                <i class="ri-money-dollar-circle-line text-xl text-yellow-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Tahsilat</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Ürünler -->
    <?php if (sayfaErisimKontrol($pdo, 'urunler.php')): ?>
    <a href="urunler.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-indigo-100">
                <i class="ri-box-3-line text-xl text-indigo-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Ürünler</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Müşteriler -->
    <?php if (sayfaErisimKontrol($pdo, 'musteriler.php')): ?>
    <a href="musteriler.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-orange-100">
                <i class="ri-team-line text-xl text-orange-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Müşteriler</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Tedarikçiler -->
    <?php if (sayfaErisimKontrol($pdo, 'tedarikciler.php')): ?>
    <a href="tedarikciler.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-lime-100">
                <i class="ri-truck-line text-xl text-lime-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Tedarikçiler</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Kullanıcılar -->
    <?php if (sayfaErisimKontrol($pdo, 'kullanicilar.php')): ?>
    <a href="kullanicilar.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-pink-100">
                <i class="ri-user-line text-xl text-pink-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Kullanıcılar</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Roller -->
    <?php if (sayfaErisimKontrol($pdo, 'roller.php')): ?>
    <a href="roller.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-red-100">
                <i class="ri-shield-user-line text-xl text-red-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Roller</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Raporlar -->
    <?php if (sayfaErisimKontrol($pdo, 'raporlar.php')): ?>
    <a href="raporlar.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-cyan-100">
                <i class="ri-bar-chart-line text-xl text-cyan-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Raporlar</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Gün Sonu -->
    <?php if (sayfaErisimKontrol($pdo, 'gunsonu.php')): ?>
    <a href="gunsonu.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-purple-100">
                <i class="ri-time-line text-xl text-purple-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Gün Sonu</span>
        </div>
    </a>
    <?php endif; ?>

    <!-- Ayarlar -->
    <?php if (sayfaErisimKontrol($pdo, 'ayarlar.php')): ?>
    <a href="ayarlar.php" class="block p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition-shadow">
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 mb-3 flex items-center justify-center rounded-full bg-gray-100">
                <i class="ri-settings-line text-xl text-gray-500"></i>
            </div>
            <span class="text-gray-700 text-sm font-medium">Ayarlar</span>
        </div>
    </a>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>

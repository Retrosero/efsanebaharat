<?php
// stok_kg_ekle.php
require_once 'includes/db.php';
include 'includes/header.php';

$isSuccess = false;
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_add'])) {
    try {
        // 1. stok_kg alanını ekle
        $pdo->exec("ALTER TABLE `urunler` ADD COLUMN `stok_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `stok_miktari`");
        
        // 2. Mevcut stok değerlerini stok_kg alanına kopyala
        // Kilogram cinsinden olanlar doğrudan aktarılacak
        $pdo->exec("UPDATE `urunler` SET `stok_kg` = `stok_miktari` WHERE `olcum_birimi` = 'kg'");
        
        // 3. Gram cinsinden olan ürünleri kg'a çevirerek aktar (1000 gr = 1 kg)
        $pdo->exec("UPDATE `urunler` SET `stok_kg` = `stok_miktari` / 1000 WHERE `olcum_birimi` = 'gr'");
        
        $isSuccess = true;
    } catch (PDOException $e) {
        $errorMsg = $e->getMessage();
    }
}

?>

<div class="p-4 max-w-3xl mx-auto">
    <div class="bg-white shadow-sm rounded-lg p-6">
        <h1 class="text-xl font-semibold mb-4">Stok KG Alanı Ekleme</h1>
        
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isSuccess): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                <div class="font-bold">Başarılı!</div>
                <p>Stok_kg alanı başarıyla eklendi ve mevcut değerler dönüştürüldü.</p>
                <div class="mt-3">
                    <a href="urunler.php" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Ürünler Sayfasına Dön
                    </a>
                </div>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isSuccess): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <div class="font-bold">Hata!</div>
                <p>Stok_kg alanı eklenirken bir hata oluştu: <?= htmlspecialchars($errorMsg) ?></p>
            </div>
        <?php else: ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6">
                <p>Bu işlem, veritabanınızın "urunler" tablosuna "stok_kg" adında yeni bir alan ekleyecek ve mevcut stok değerlerini bu alana dönüştürecektir.</p>
                <p class="mt-2">Bu sayede kg ve gram cinsinden olan ürünlerin stok bilgileri daha doğru görüntülenecektir.</p>
                <p class="mt-2 font-semibold">İşlem geri alınamaz, devam etmeden önce veritabanı yedeği almanız önerilir.</p>
            </div>
            
            <div class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
                <h2 class="text-lg font-medium mb-2">Yapılacak İşlemler:</h2>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Urunler tablosuna stok_kg (DECIMAL(10,3)) alanı eklenecek</li>
                    <li>"kg" birimindeki ürünlerin stok değerleri doğrudan aktarılacak</li>
                    <li>"gr" birimindeki ürünlerin stok değerleri 1000'e bölünerek kg'a çevrilecek</li>
                    <li>Diğer birimlerdeki ürünlerin stok_kg değeri 0 olarak kalacak</li>
                </ul>
            </div>
            
            <div class="flex items-center justify-between">
                <a href="urunler.php" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    İptal
                </a>
                
                <form method="post">
                    <button type="submit" name="confirm_add" class="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        Onayla ve Alanı Ekle
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 
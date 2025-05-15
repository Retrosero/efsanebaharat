<?php
// kurulum.php - Veritabanı tablolarını oluşturma ve örnek verileri ekleme

// Veritabanı bağlantısı
require_once 'includes/db.php';

// Hata mesajlarını göster
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Başarı ve hata mesajları
$messages = [];
$errors = [];

try {
    // Banka Listesi Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `banka_listesi` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `banka_adi` VARCHAR(100) NOT NULL,
          `durum` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $messages[] = "Banka listesi tablosu oluşturuldu.";
    
    // Markalar Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `markalar` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `marka_adi` VARCHAR(100) NOT NULL,
          `durum` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $messages[] = "Markalar tablosu oluşturuldu.";
    
    // Kategoriler Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `kategoriler` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `kategori_adi` VARCHAR(100) NOT NULL,
          `durum` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $messages[] = "Kategoriler tablosu oluşturuldu.";
    
    // Alt Kategoriler Tablosu
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `alt_kategoriler` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `kategori_id` INT NOT NULL,
          `alt_kategori_adi` VARCHAR(100) NOT NULL,
          `durum` TINYINT(1) DEFAULT 1,
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    ");
    $messages[] = "Alt kategoriler tablosu oluşturuldu.";
    
    // Örnek veriler eklensin mi?
    if (isset($_GET['ornek_veri']) && $_GET['ornek_veri'] == 1) {
        // Banka Listesi için örnek veriler
        $pdo->exec("
            INSERT INTO `banka_listesi` (`banka_adi`) VALUES 
            ('Ziraat Bankası'),
            ('İş Bankası'),
            ('Garanti Bankası'),
            ('Akbank'),
            ('Yapı Kredi Bankası');
        ");
        $messages[] = "Banka listesi için örnek veriler eklendi.";
        
        // Markalar için örnek veriler
        $pdo->exec("
            INSERT INTO `markalar` (`marka_adi`) VALUES 
            ('Efsane Baharat'),
            ('Karabiber'),
            ('Tarçın'),
            ('Kimyon'),
            ('Zerdeçal');
        ");
        $messages[] = "Markalar için örnek veriler eklendi.";
        
        // Kategoriler için örnek veriler
        $pdo->exec("
            INSERT INTO `kategoriler` (`kategori_adi`) VALUES 
            ('Baharatlar'),
            ('Kuruyemişler'),
            ('Şifalı Bitkiler'),
            ('Çaylar'),
            ('Yemeklik Ürünler');
        ");
        $messages[] = "Kategoriler için örnek veriler eklendi.";
        
        // Alt Kategoriler için örnek veriler
        $pdo->exec("
            INSERT INTO `alt_kategoriler` (`kategori_id`, `alt_kategori_adi`) VALUES 
            (1, 'Toz Baharatlar'),
            (1, 'Tane Baharatlar'),
            (1, 'Karışım Baharatlar'),
            (2, 'Çiğ Kuruyemişler'),
            (2, 'Kavrulmuş Kuruyemişler'),
            (3, 'Bitki Çayları'),
            (3, 'Kuru Bitkiler'),
            (4, 'Siyah Çaylar'),
            (4, 'Yeşil Çaylar'),
            (5, 'Bakliyatlar'),
            (5, 'Tahıllar');
        ");
        $messages[] = "Alt kategoriler için örnek veriler eklendi.";
    }
    
} catch (PDOException $e) {
    $errors[] = "Veritabanı hatası: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Kurulumu</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="max-w-4xl mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">Veritabanı Kurulumu</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                <h3 class="font-bold">Hatalar:</h3>
                <ul class="list-disc ml-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($messages)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4">
                <h3 class="font-bold">Başarılı İşlemler:</h3>
                <ul class="list-disc ml-5">
                    <?php foreach ($messages as $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Kurulum Tamamlandı</h2>
            <p class="mb-4">Veritabanı tabloları başarıyla oluşturuldu. Aşağıdaki bağlantıları kullanarak devam edebilirsiniz:</p>
            
            <div class="flex flex-col sm:flex-row gap-4 mt-6">
                <a href="ayarlar.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 text-center">
                    Ayarlar Sayfasına Git
                </a>
                <a href="kurulum.php?ornek_veri=1" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 text-center">
                    Örnek Verileri Ekle
                </a>
                <a href="index.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 text-center">
                    Ana Sayfaya Dön
                </a>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h2 class="text-xl font-semibold mb-4">Tablo Yapıları</h2>
            
            <div class="mb-6">
                <h3 class="font-medium text-lg mb-2">Banka Listesi Tablosu</h3>
                <pre class="bg-gray-100 p-4 rounded overflow-x-auto">
CREATE TABLE IF NOT EXISTS `banka_listesi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `banka_adi` VARCHAR(100) NOT NULL,
  `durum` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</pre>
            </div>
            
            <div class="mb-6">
                <h3 class="font-medium text-lg mb-2">Markalar Tablosu</h3>
                <pre class="bg-gray-100 p-4 rounded overflow-x-auto">
CREATE TABLE IF NOT EXISTS `markalar` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `marka_adi` VARCHAR(100) NOT NULL,
  `durum` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</pre>
            </div>
            
            <div class="mb-6">
                <h3 class="font-medium text-lg mb-2">Kategoriler Tablosu</h3>
                <pre class="bg-gray-100 p-4 rounded overflow-x-auto">
CREATE TABLE IF NOT EXISTS `kategoriler` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori_adi` VARCHAR(100) NOT NULL,
  `durum` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);</pre>
            </div>
            
            <div>
                <h3 class="font-medium text-lg mb-2">Alt Kategoriler Tablosu</h3>
                <pre class="bg-gray-100 p-4 rounded overflow-x-auto">
CREATE TABLE IF NOT EXISTS `alt_kategoriler` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kategori_id` INT NOT NULL,
  `alt_kategori_adi` VARCHAR(100) NOT NULL,
  `durum` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler`(`id`) ON DELETE CASCADE
);</pre>
            </div>
        </div>
    </div>
</body>
</html> 
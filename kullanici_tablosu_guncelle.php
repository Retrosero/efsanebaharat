<?php
// kullanici_tablosu_guncelle.php
require_once 'includes/db.php';

// Hata mesajlarını göster
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Başarı ve hata mesajları
$messages = [];
$errors = [];

try {
    // Kullanıcılar tablosunda telefon sütunu var mı kontrol et
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'telefon'");
    if ($stmt->rowCount() == 0) {
        // Telefon sütunu ekle
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN telefon VARCHAR(20) DEFAULT NULL");
        $messages[] = "Telefon sütunu eklendi.";
    } else {
        $messages[] = "Telefon sütunu zaten mevcut.";
    }
    
    // Kullanıcılar tablosunda eposta_bildirim sütunu var mı kontrol et
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'eposta_bildirim'");
    if ($stmt->rowCount() == 0) {
        // eposta_bildirim sütunu ekle
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN eposta_bildirim TINYINT(1) DEFAULT 1");
        $messages[] = "E-posta bildirim sütunu eklendi.";
    } else {
        $messages[] = "E-posta bildirim sütunu zaten mevcut.";
    }
    
    // Kullanıcılar tablosunda sms_bildirim sütunu var mı kontrol et
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar LIKE 'sms_bildirim'");
    if ($stmt->rowCount() == 0) {
        // sms_bildirim sütunu ekle
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN sms_bildirim TINYINT(1) DEFAULT 0");
        $messages[] = "SMS bildirim sütunu eklendi.";
    } else {
        $messages[] = "SMS bildirim sütunu zaten mevcut.";
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
    <title>Kullanıcı Tablosu Güncelleme</title>
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
        <h1 class="text-2xl font-bold mb-6">Kullanıcı Tablosu Güncelleme</h1>
        
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
            <h2 class="text-xl font-semibold mb-4">Güncelleme Tamamlandı</h2>
            <p class="mb-4">Kullanıcı tablosu başarıyla güncellendi. Aşağıdaki bağlantıları kullanarak devam edebilirsiniz:</p>
            
            <div class="flex flex-col sm:flex-row gap-4 mt-6">
                <a href="ayarlar.php" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 text-center">
                    Ayarlar Sayfasına Git
                </a>
                <a href="index.php" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 text-center">
                    Ana Sayfaya Dön
                </a>
            </div>
        </div>
    </div>
</body>
</html> 
<?php
// musteri_ekle.php

// Önbellek kontrolü - sayfanın her zaman taze verilerle yüklenmesini sağlar
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Hata ayıklama için
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısını kontrol et
try {
    require_once 'includes/db.php';
    
    // Bağlantıyı test et
    $testQuery = $pdo->query("SELECT 1");
    if (!$testQuery) {
        throw new Exception("Veritabanı bağlantısı başarılı ancak sorgu çalıştırılamadı.");
    }
    
    // Müşteri kodu alanının veri tipini kontrol et
    try {
        $tableInfoQuery = $pdo->query("SHOW COLUMNS FROM musteriler WHERE Field = 'musteri_kodu'");
        $columnInfo = $tableInfoQuery->fetch(PDO::FETCH_ASSOC);
        error_log("Müşteri kodu alan bilgisi: " . print_r($columnInfo, true));
        
        // Eğer müşteri_kodu alanı yoksa, ekleyelim
        if (!$columnInfo) {
            $pdo->exec("ALTER TABLE musteriler ADD COLUMN musteri_kodu VARCHAR(50) DEFAULT NULL");
            error_log("Müşteri kodu alanı eklendi.");
        }
        // Eğer müşteri_kodu alanı TINYINT veya INT tipindeyse VARCHAR'a çevirelim
        else if (strpos($columnInfo['Type'], 'int') !== false || strpos($columnInfo['Type'], 'tinyint') !== false) {
            $pdo->exec("ALTER TABLE musteriler MODIFY COLUMN musteri_kodu VARCHAR(50) DEFAULT NULL");
            error_log("Müşteri kodu alanı VARCHAR(50) olarak değiştirildi.");
        }
    } catch (Exception $e) {
        error_log("Tablo bilgisi alınamadı: " . $e->getMessage());
    }
} catch (Exception $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Müşteri tipleri listesini çek
try {
    $stmt = $pdo->query("SELECT * FROM musteri_tipleri ORDER BY tip_adi");
    $musteri_tipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Hata durumunda boş bir dizi oluştur
    $musteri_tipleri = [];
    $errorMessage = "Müşteri tipleri yüklenirken hata oluştu: " . $e->getMessage();
}

// Benzersiz müşteri kodu oluştur
function generateUniqueCustomerCode($pdo) {
    try {
        $prefix = "MUS";
        $unique = false;
        $code = "";
        $maxAttempts = 10; // Maksimum deneme sayısı
        $attempts = 0;
        
        while (!$unique && $attempts < $maxAttempts) {
            $attempts++;
            
            // 6 haneli rastgele sayı oluştur
            $randomNumber = mt_rand(100000, 999999);
            $code = $prefix . $randomNumber;
            
            // Kodun benzersiz olup olmadığını kontrol et
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM musteriler WHERE musteri_kodu = :code");
            $stmt->execute([':code' => $code]);
            $count = $stmt->fetchColumn();
            
            if ($count == 0) {
                $unique = true;
            }
        }
        
        // Eğer maksimum deneme sayısına ulaşıldıysa zaman damgası kullan
        if (!$unique) {
            $code = $prefix . time();
        }
        
        return $code;
    } catch (Exception $e) {
        // Hata durumunda zaman damgası ile kod oluştur
        return $prefix . time();
    }
}

// Müşteri kodu oluşturmayı try-catch bloğu içine alalım
try {
    $musteri_kodu = generateUniqueCustomerCode($pdo);
} catch (Exception $e) {
    $musteri_kodu = "MUS" . time(); // Hata durumunda zaman damgası ile kod oluştur
}

$successMessage = '';
$errorMessage = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ad = trim($_POST['ad'] ?? '');
        $soyad = trim($_POST['soyad'] ?? '');
        $telefon = trim($_POST['telefon'] ?? '');
        $adres = trim($_POST['adres'] ?? '');
        $vergi_no = trim($_POST['vergi_no'] ?? '');
        $tip_id = isset($_POST['tip_id']) && !empty($_POST['tip_id']) ? intval($_POST['tip_id']) : null;
        $email = trim($_POST['email'] ?? '');
        $vergi_dairesi = trim($_POST['vergi_dairesi'] ?? '');
        
        // Müşteri kodunu doğrudan formdan al
        $musteri_kodu = isset($_POST['musteri_kodu']) ? trim($_POST['musteri_kodu']) : '';
        
        // Eğer müşteri kodu boşsa, otomatik oluştur
        if (empty($musteri_kodu)) {
            $musteri_kodu = generateUniqueCustomerCode($pdo);
        }
        
        // Müşteri kodunun string olduğundan emin ol
        $musteri_kodu = (string)$musteri_kodu;
        
        // Debug için
        error_log("Müşteri kodu (form): " . $musteri_kodu);
        error_log("Müşteri kodu (tip): " . gettype($musteri_kodu));
        
        // Validasyon
        if (empty($ad)) {
            $errorMessage = 'Müşteri adı boş olamaz.';
        } else {
            // Müşteri kodunun benzersiz olup olmadığını kontrol et
            if (!empty($musteri_kodu)) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM musteriler WHERE musteri_kodu = :code");
                $stmt->execute([':code' => $musteri_kodu]);
                $count = $stmt->fetchColumn();
                
                if ($count > 0) {
                    $errorMessage = 'Bu müşteri kodu zaten kullanılıyor. Lütfen başka bir kod girin.';
                }
            }
            
            if (empty($errorMessage)) {
                // SQL sorgusunu hazırla
                $sql = "INSERT INTO musteriler (ad, soyad, telefon, adres, vergi_no";
                
                // Eğer tip_id varsa SQL'e ekle
                if ($tip_id !== null) {
                    $sql .= ", tip_id";
                }
                
                // Müşteri kodunu her zaman ekle, boş olsa bile
                $sql .= ", musteri_kodu";
                
                // Email ve vergi dairesi alanlarını ekle
                $sql .= ", email, vergi_dairesi";
                
                $sql .= ") VALUES (:ad, :soyad, :telefon, :adres, :vergi_no";
                
                if ($tip_id !== null) {
                    $sql .= ", :tip_id";
                }
                
                // Müşteri kodunu her zaman ekle
                $sql .= ", :musteri_kodu";
                
                // Email ve vergi dairesi değerlerini ekle
                $sql .= ", :email, :vergi_dairesi";
                
                $sql .= ")";
                
                // Debug için
                error_log("SQL: " . $sql);
                
                // Sorguyu hazırla
                $stmt = $pdo->prepare($sql);
                
                // Parametreleri hazırla
                $params = [
                    ':ad' => $ad,
                    ':soyad' => $soyad,
                    ':telefon' => $telefon,
                    ':adres' => $adres,
                    ':vergi_no' => $vergi_no,
                    ':musteri_kodu' => (string)$musteri_kodu, // Müşteri kodunu string olarak ekle
                    ':email' => $email, // Email parametresini ekle
                    ':vergi_dairesi' => $vergi_dairesi // Vergi dairesi parametresini ekle
                ];
                
                // Opsiyonel parametreleri ekle
                if ($tip_id !== null) {
                    $params[':tip_id'] = $tip_id;
                }
                
                // Debug için
                error_log("Params: " . print_r($params, true));
                
                // Sorguyu çalıştır
                $stmt->execute($params);
                
                // Son eklenen ID'yi al
                $lastInsertId = $pdo->lastInsertId();
                
                // Eklenen müşteriyi kontrol et
                $checkStmt = $pdo->prepare("SELECT * FROM musteriler WHERE id = :id");
                $checkStmt->execute([':id' => $lastInsertId]);
                $insertedCustomer = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug için
                error_log("Eklenen müşteri: " . print_r($insertedCustomer, true));
                
                $successMessage = 'Müşteri başarıyla eklendi.';
                
                // Müşteriler sayfasına yönlendir
                header("Location: musteriler.php?mesaj=eklendi");
                exit;
            }
        }
    } catch (PDOException $e) {
        $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
    } catch (Exception $e) {
        $errorMessage = 'Hata: ' . $e->getMessage();
    }
}

// Özel header - Geri butonunu navbar'a eklemek için
$pageTitle = "Yeni Müşteri Ekleme";
$showBackButton = true;
$backUrl = "musteriler.php";

try {
    include 'includes/header.php';
} catch (Exception $e) {
    die("Header dosyası yüklenirken hata oluştu: " . $e->getMessage());
}
?>

<style>
body {
  background-color: white;
}
</style>

<div class="p-0">
    <div class="max-w-full mx-auto">
        <div class="bg-white shadow-sm p-6">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800">Yeni Müşteri Ekle</h1>
                <a href="musteriler.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-button flex items-center">
                    <i class="ri-arrow-left-line mr-2"></i> Müşteri Listesine Dön
                </a>
            </div>
            
            <?php if ($successMessage): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?= htmlspecialchars($successMessage) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($errorMessage): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?= htmlspecialchars($errorMessage) ?></p>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Müşteri Kodu -->
                    <div>
                        <label for="musteri_kodu" class="block text-sm font-medium text-gray-700 mb-2">Müşteri Kodu</label>
                        <input 
                            type="text" 
                            id="musteri_kodu" 
                            name="musteri_kodu" 
                            value="<?= htmlspecialchars($_POST['musteri_kodu'] ?? $musteri_kodu) ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Müşteri kodu girin veya otomatik oluşturulmasını bekleyin"
                        >
                        <p class="text-xs text-gray-500 mt-1">Boş bırakırsanız otomatik kod oluşturulacaktır.</p>
                    </div>
                    
                    <!-- Müşteri Tipi -->
                    <div>
                        <label for="tip_id" class="block text-sm font-medium text-gray-700 mb-2">Müşteri Tipi</label>
                        <select 
                            id="tip_id" 
                            name="tip_id" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                            <option value="">Seçiniz</option>
                            <?php foreach ($musteri_tipleri as $tip): ?>
                            <option value="<?= $tip['id'] ?>" <?= isset($_POST['tip_id']) && $_POST['tip_id'] == $tip['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tip['tip_adi']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Ad -->
                    <div>
                        <label for="ad" class="block text-sm font-medium text-gray-700 mb-2">Ad <span class="text-red-600">*</span></label>
                        <input 
                            type="text" 
                            id="ad" 
                            name="ad" 
                            value="<?= htmlspecialchars($_POST['ad'] ?? '') ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                            required
                        >
                    </div>
                    
                    <!-- Soyad -->
                    <div>
                        <label for="soyad" class="block text-sm font-medium text-gray-700 mb-2">Soyad</label>
                        <input 
                            type="text" 
                            id="soyad" 
                            name="soyad" 
                            value="<?= htmlspecialchars($_POST['soyad'] ?? '') ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                    </div>
                    
                    <!-- Telefon -->
                    <div>
                        <label for="telefon" class="block text-sm font-medium text-gray-700 mb-2">Telefon</label>
                        <input 
                            type="tel" 
                            id="telefon" 
                            name="telefon" 
                            value="<?= htmlspecialchars($_POST['telefon'] ?? '') ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                    </div>
                    
                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                            placeholder="ornek@domain.com"
                        >
                    </div>
                    
                    <!-- Vergi No -->
                    <div>
                        <label for="vergi_no" class="block text-sm font-medium text-gray-700 mb-2">Vergi/TC No</label>
                        <input 
                            type="text" 
                            id="vergi_no" 
                            name="vergi_no" 
                            value="<?= htmlspecialchars($_POST['vergi_no'] ?? '') ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        >
                    </div>
                    
                    <!-- Vergi Dairesi -->
                    <div>
                        <label for="vergi_dairesi" class="block text-sm font-medium text-gray-700 mb-2">Vergi Dairesi</label>
                        <input 
                            type="text" 
                            id="vergi_dairesi" 
                            name="vergi_dairesi" 
                            value="<?= htmlspecialchars($_POST['vergi_dairesi'] ?? '') ?>" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                            placeholder="Vergi dairesinin adını girin"
                        >
                    </div>
                    
                    <!-- Adres -->
                    <div class="md:col-span-2">
                        <label for="adres" class="block text-sm font-medium text-gray-700 mb-2">Adres</label>
                        <textarea 
                            id="adres" 
                            name="adres" 
                            rows="3" 
                            class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        ><?= htmlspecialchars($_POST['adres'] ?? '') ?></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <button 
                        type="submit" 
                        class="bg-primary hover:bg-blue-600 text-white px-6 py-2 rounded-button"
                    >
                        Müşteri Ekle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
try {
    include 'includes/footer.php';
} catch (Exception $e) {
    echo "Footer dosyası yüklenirken hata oluştu: " . $e->getMessage();
}
?>

<?php
// kullanici_duzenle.php
require_once 'includes/db.php';
include 'includes/header.php';

// Kullanıcı ID'sini al
$kullanici_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Kullanıcı bilgilerini çek
$kullanici = null;
try {
    $stmt = $pdo->prepare("
        SELECT k.*, r.rol_adi
        FROM kullanicilar k
        JOIN roller r ON k.rol_id = r.id
        WHERE k.id = :id
    ");
    $stmt->execute([':id' => $kullanici_id]);
    $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Hata: " . $e->getMessage();
}

// Kullanıcı bulunamadıysa
if (!$kullanici) {
    echo "<div class='p-4 text-red-600'>Kullanıcı bulunamadı.</div>";
    include 'includes/footer.php';
    exit;
}

// Rolleri çek
try {
    $stmt = $pdo->query("SELECT * FROM roller ORDER BY rol_adi");
    $roller = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Hata: " . $e->getMessage();
    $roller = [];
}

// Sayfaları çek
try {
    $stmt = $pdo->query("SELECT * FROM sayfalar ORDER BY sayfa_adi");
    $sayfalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Hata: " . $e->getMessage();
    $sayfalar = [];
}

// Kullanıcının rol izinlerini çek
$rol_izinleri = [];
try {
    $stmt = $pdo->prepare("
        SELECT sayfa_id, izin
        FROM rol_sayfa_izinleri
        WHERE rol_id = :rol_id
    ");
    $stmt->execute([':rol_id' => $kullanici['rol_id']]);
    $izinler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($izinler as $izin) {
        $rol_izinleri[$izin['sayfa_id']] = $izin['izin'];
    }
} catch(Exception $e) {
    // Hata durumunda boş dizi kullan
}

// Form gönderildiğinde
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $eposta = trim($_POST['eposta'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';
    $rol_id = intval($_POST['rol_id'] ?? 0);
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Validasyon
    $hatalar = [];
    
    if (empty($kullanici_adi)) {
        $hatalar[] = "Kullanıcı adı gereklidir.";
    }
    
    if (empty($eposta)) {
        $hatalar[] = "E-posta adresi gereklidir.";
    } elseif (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = "Geçerli bir e-posta adresi giriniz.";
    }
    
    // Şifre değiştirilecekse kontrol et
    $sifre_degistir = false;
    if (!empty($sifre)) {
        $sifre_degistir = true;
        
        if (strlen($sifre) < 6) {
            $hatalar[] = "Şifre en az 6 karakter olmalıdır.";
        } elseif ($sifre !== $sifre_tekrar) {
            $hatalar[] = "Şifreler eşleşmiyor.";
        }
    }
    
    if ($rol_id <= 0) {
        $hatalar[] = "Lütfen bir rol seçin.";
    }
    
    // Kullanıcı adı ve e-posta benzersiz olmalı (kendisi hariç)
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = :kullanici_adi AND id != :id");
        $stmt->execute([':kullanici_adi' => $kullanici_adi, ':id' => $kullanici_id]);
        if ($stmt->fetchColumn() > 0) {
            $hatalar[] = "Bu kullanıcı adı zaten kullanılıyor.";
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar WHERE eposta = :eposta AND id != :id");
        $stmt->execute([':eposta' => $eposta, ':id' => $kullanici_id]);
        if ($stmt->fetchColumn() > 0) {
            $hatalar[] = "Bu e-posta adresi zaten kullanılıyor.";
        }
    } catch(Exception $e) {
        $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
    }
    
    // Hata yoksa güncelle
    if (empty($hatalar)) {
        try {
            $pdo->beginTransaction();
            
            // Şifre değiştirilecekse
            if ($sifre_degistir) {
                $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("
                    UPDATE kullanicilar 
                    SET kullanici_adi = :kullanici_adi, 
                        eposta = :eposta, 
                        sifre = :sifre, 
                        rol_id = :rol_id, 
                        aktif = :aktif
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    ':kullanici_adi' => $kullanici_adi,
                    ':eposta' => $eposta,
                    ':sifre' => $hashed_password,
                    ':rol_id' => $rol_id,
                    ':aktif' => $aktif,
                    ':id' => $kullanici_id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE kullanicilar 
                    SET kullanici_adi = :kullanici_adi, 
                        eposta = :eposta, 
                        rol_id = :rol_id, 
                        aktif = :aktif
                    WHERE id = :id
                ");
                
                $stmt->execute([
                    ':kullanici_adi' => $kullanici_adi,
                    ':eposta' => $eposta,
                    ':rol_id' => $rol_id,
                    ':aktif' => $aktif,
                    ':id' => $kullanici_id
                ]);
            }
            
            $pdo->commit();
            
            // Başarılı güncelleme sonrası yönlendirme
            header("Location: kullanicilar.php?mesaj=guncellendi");
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $errorMessage = "Kullanıcı güncellenemedi: " . $e->getMessage();
        }
    } else {
        $errorMessage = implode("<br>", $hatalar);
    }
}
?>

<div class="p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold">Kullanıcı Düzenle</h1>
        <a href="kullanicilar.php" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200 flex items-center gap-1">
            <i class="ri-arrow-left-line"></i> Kullanıcılara Dön
        </a>
    </div>

    <?php if ($successMessage): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?= $successMessage ?></p>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?= $errorMessage ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <form method="post" class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Kullanıcı Adı -->
                <div>
                    <label for="kullanici_adi" class="block text-sm font-medium text-gray-700 mb-1">Kullanıcı Adı</label>
                    <input 
                        type="text" 
                        id="kullanici_adi" 
                        name="kullanici_adi" 
                        value="<?= htmlspecialchars($_POST['kullanici_adi'] ?? $kullanici['kullanici_adi']) ?>" 
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                </div>

                <!-- E-posta -->
                <div>
                    <label for="eposta" class="block text-sm font-medium text-gray-700 mb-1">E-posta</label>
                    <input 
                        type="email" 
                        id="eposta" 
                        name="eposta" 
                        value="<?= htmlspecialchars($_POST['eposta'] ?? $kullanici['eposta']) ?>" 
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                </div>

                <!-- Şifre -->
                <div>
                    <label for="sifre" class="block text-sm font-medium text-gray-700 mb-1">Şifre</label>
                    <input 
                        type="password" 
                        id="sifre" 
                        name="sifre" 
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                    <p class="mt-1 text-xs text-gray-500">Değiştirmek istemiyorsanız boş bırakın.</p>
                </div>

                <!-- Şifre Tekrar -->
                <div>
                    <label for="sifre_tekrar" class="block text-sm font-medium text-gray-700 mb-1">Şifre Tekrar</label>
                    <input 
                        type="password" 
                        id="sifre_tekrar" 
                        name="sifre_tekrar" 
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    >
                </div>

                <!-- Rol -->
                <div>
                    <label for="rol_id" class="block text-sm font-medium text-gray-700 mb-1">Rol</label>
                    <select 
                        id="rol_id" 
                        name="rol_id" 
                        class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                        <option value="">Rol Seçin</option>
                        <?php foreach($roller as $rol): ?>
                            <option value="<?= $rol['id'] ?>" <?= (isset($_POST['rol_id']) ? $_POST['rol_id'] == $rol['id'] : $kullanici['rol_id'] == $rol['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rol['rol_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Aktif/Pasif -->
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="aktif" 
                        name="aktif" 
                        class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                        <?= (isset($_POST['aktif']) ? $_POST['aktif'] : $kullanici['aktif']) ? 'checked' : '' ?>
                    >
                    <label for="aktif" class="ml-2 block text-sm text-gray-900">
                        Aktif
                    </label>
                </div>
            </div>

            <!-- Rol İzinleri Bilgisi -->
            <?php if (!empty($sayfalar) && !empty($rol_izinleri)): ?>
                <div class="mt-8">
                    <h2 class="text-lg font-medium mb-4">Rol İzinleri</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <p class="text-sm text-gray-600 mb-4">
                            Bu kullanıcı <strong><?= htmlspecialchars($kullanici['rol_adi']) ?></strong> rolüne sahiptir. 
                            Bu role ait sayfa erişim izinleri aşağıda listelenmiştir. 
                            İzinleri değiştirmek için <a href="roller.php" class="text-primary hover:underline">Roller</a> sayfasını ziyaret edin.
                        </p>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <?php foreach($sayfalar as $sayfa): ?>
                                <div class="flex items-center">
                                    <span class="<?= isset($rol_izinleri[$sayfa['id']]) && $rol_izinleri[$sayfa['id']] ? 'text-green-600' : 'text-red-600' ?> mr-2">
                                        <i class="<?= isset($rol_izinleri[$sayfa['id']]) && $rol_izinleri[$sayfa['id']] ? 'ri-check-line' : 'ri-close-line' ?>"></i>
                                    </span>
                                    <span class="text-sm"><?= htmlspecialchars($sayfa['sayfa_adi']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-6 flex justify-end">
                <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                    <i class="ri-save-line mr-1"></i> Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 
<?php
require_once 'includes/db.php';
include 'includes/header.php';

$successMessage = '';
$errorMessage = '';

// Kullanıcı bilgilerini çek
$kullanici_id = $_SESSION['kullanici_id'] ?? 0;
$kullanici = [];

if ($kullanici_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = :id");
        $stmt->execute([':id' => $kullanici_id]);
        $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = 'Kullanıcı bilgileri alınırken hata oluştu: ' . $e->getMessage();
    }
}

// Kullanıcı profil bilgilerini güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $eposta = trim($_POST['eposta'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    
    if (empty($ad_soyad) || empty($eposta)) {
        $errorMessage = 'Ad Soyad ve E-posta alanları zorunludur.';
    } else {
        try {
            // Kullanıcı adı ve soyadını ayır
            $ad_soyad_parts = explode(' ', $ad_soyad);
            $soyad = array_pop($ad_soyad_parts); // Son kelimeyi soyad olarak al
            $ad = implode(' ', $ad_soyad_parts); // Geri kalan kısmı ad olarak al
            
            $stmt = $pdo->prepare("UPDATE kullanicilar SET 
                kullanici_adi = :kullanici_adi, 
                eposta = :eposta, 
                telefon = :telefon 
                WHERE id = :id");
                
            $stmt->execute([
                ':kullanici_adi' => $ad_soyad,
                ':eposta' => $eposta,
                ':telefon' => $telefon,
                ':id' => $kullanici_id
            ]);
            
            $successMessage = 'Profil bilgileriniz başarıyla güncellendi.';
            
            // Güncellenmiş kullanıcı bilgilerini yeniden çek
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = :id");
            $stmt->execute([':id' => $kullanici_id]);
            $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Session bilgilerini güncelle
            $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
            $_SESSION['eposta'] = $kullanici['eposta'];
            
        } catch (PDOException $e) {
            $errorMessage = 'Profil güncellenirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Şifre değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $mevcut_sifre = $_POST['mevcut_sifre'] ?? '';
    $yeni_sifre = $_POST['yeni_sifre'] ?? '';
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'] ?? '';
    
    if (empty($mevcut_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
        $errorMessage = 'Tüm şifre alanları zorunludur.';
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $errorMessage = 'Yeni şifreler eşleşmiyor.';
    } elseif (strlen($yeni_sifre) < 6) {
        $errorMessage = 'Yeni şifre en az 6 karakter olmalıdır.';
    } else {
        try {
            // Mevcut şifreyi kontrol et
            $stmt = $pdo->prepare("SELECT sifre FROM kullanicilar WHERE id = :id");
            $stmt->execute([':id' => $kullanici_id]);
            $hash = $stmt->fetchColumn();
            
            if (password_verify($mevcut_sifre, $hash)) {
                // Yeni şifreyi hashle ve güncelle
                $yeni_hash = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE kullanicilar SET sifre = :sifre WHERE id = :id");
                $stmt->execute([
                    ':sifre' => $yeni_hash,
                    ':id' => $kullanici_id
                ]);
                
                $successMessage = 'Şifreniz başarıyla güncellendi.';
            } else {
                $errorMessage = 'Mevcut şifre yanlış.';
            }
        } catch (PDOException $e) {
            $errorMessage = 'Şifre güncellenirken hata oluştu: ' . $e->getMessage();
        }
    }
}

// Banka, Marka, Kategori ve Alt Kategori İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_bank':
                $banka_adi = trim($_POST['banka_adi'] ?? '');
                if (!empty($banka_adi)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO banka_listesi (banka_adi) VALUES (:adi)");
                        $stmt->execute([':adi' => $banka_adi]);
                        $successMessage = 'Banka başarıyla eklendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Banka eklenirken hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Banka adı boş olamaz.';
                }
                break;

            case 'update_bank':
                $banka_id = $_POST['banka_id'] ?? 0;
                $banka_adi = trim($_POST['banka_adi'] ?? '');
                $durum = isset($_POST['durum']) ? 1 : 0;
                if (!empty($banka_adi) && $banka_id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE banka_listesi SET banka_adi = :adi, durum = :durum WHERE id = :id");
                        $stmt->execute([
                            ':adi' => $banka_adi,
                            ':durum' => $durum,
                            ':id' => $banka_id
                        ]);
                        $successMessage = 'Banka bilgileri güncellendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Banka adı boş olamaz veya geçersiz banka ID.';
                }
                break;

            case 'delete_bank':
                $banka_id = $_POST['banka_id'] ?? 0;
                if ($banka_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM banka_listesi WHERE id = :id");
                        $stmt->execute([':id' => $banka_id]);
                        $successMessage = 'Banka başarıyla silindi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Silme işlemi sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Geçersiz banka ID.';
                }
                break;

            case 'add_brand':
                $marka_adi = trim($_POST['marka_adi'] ?? '');
                if (!empty($marka_adi)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO markalar (marka_adi) VALUES (:adi)");
                        $stmt->execute([':adi' => $marka_adi]);
                        $successMessage = 'Marka başarıyla eklendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Marka eklenirken hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Marka adı boş olamaz.';
                }
                break;

            case 'update_brand':
                $marka_id = $_POST['marka_id'] ?? 0;
                $marka_adi = trim($_POST['marka_adi'] ?? '');
                $durum = isset($_POST['durum']) ? 1 : 0;
                if (!empty($marka_adi) && $marka_id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE markalar SET marka_adi = :adi, durum = :durum WHERE id = :id");
                        $stmt->execute([
                            ':adi' => $marka_adi,
                            ':durum' => $durum,
                            ':id' => $marka_id
                        ]);
                        $successMessage = 'Marka bilgileri güncellendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Marka adı boş olamaz veya geçersiz marka ID.';
                }
                break;

            case 'delete_brand':
                $marka_id = $_POST['marka_id'] ?? 0;
                if ($marka_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM markalar WHERE id = :id");
                        $stmt->execute([':id' => $marka_id]);
                        $successMessage = 'Marka başarıyla silindi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Silme işlemi sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Geçersiz marka ID.';
                }
                break;

            case 'add_category':
                $kategori_adi = trim($_POST['kategori_adi'] ?? '');
                if (!empty($kategori_adi)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO kategoriler (kategori_adi) VALUES (:adi)");
                        $stmt->execute([':adi' => $kategori_adi]);
                        $successMessage = 'Kategori başarıyla eklendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Kategori eklenirken hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Kategori adı boş olamaz.';
                }
                break;

            case 'update_category':
                $kategori_id = $_POST['kategori_id'] ?? 0;
                $kategori_adi = trim($_POST['kategori_adi'] ?? '');
                $durum = isset($_POST['durum']) ? 1 : 0;
                if (!empty($kategori_adi) && $kategori_id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE kategoriler SET kategori_adi = :adi, durum = :durum WHERE id = :id");
                        $stmt->execute([
                            ':adi' => $kategori_adi,
                            ':durum' => $durum,
                            ':id' => $kategori_id
                        ]);
                        $successMessage = 'Kategori bilgileri güncellendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Kategori adı boş olamaz veya geçersiz kategori ID.';
                }
                break;

            case 'delete_category':
                $kategori_id = $_POST['kategori_id'] ?? 0;
                if ($kategori_id > 0) {
                    try {
                        // Önce alt kategorileri sil
                        $stmt = $pdo->prepare("DELETE FROM alt_kategoriler WHERE kategori_id = :id");
                        $stmt->execute([':id' => $kategori_id]);
                        // Sonra kategoriyi sil
                        $stmt = $pdo->prepare("DELETE FROM kategoriler WHERE id = :id");
                        $stmt->execute([':id' => $kategori_id]);
                        $successMessage = 'Kategori ve alt kategorileri başarıyla silindi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Silme işlemi sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Geçersiz kategori ID.';
                }
                break;

            case 'add_subcategory':
                $kategori_id = $_POST['kategori_id'] ?? 0;
                $alt_kategori_adi = trim($_POST['alt_kategori_adi'] ?? '');
                if (!empty($alt_kategori_adi) && $kategori_id > 0) {
                    try {
                        $check = $pdo->prepare("SELECT id FROM kategoriler WHERE id = :id");
                        $check->execute([':id' => $kategori_id]);
                        if ($check->rowCount() > 0) {
                            $stmt = $pdo->prepare("INSERT INTO alt_kategoriler (kategori_id, alt_kategori_adi) VALUES (:kategori_id, :adi)");
                            $stmt->execute([
                                ':kategori_id' => $kategori_id,
                                ':adi' => $alt_kategori_adi
                            ]);
                            $successMessage = 'Alt kategori başarıyla eklendi.';
                        } else {
                            $errorMessage = 'Belirtilen kategori bulunamadı.';
                        }
                    } catch (PDOException $e) {
                        $errorMessage = 'Alt kategori eklenirken hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Alt kategori adı boş olamaz veya geçersiz kategori ID.';
                }
                break;

            case 'update_subcategory':
                $alt_kategori_id = $_POST['alt_kategori_id'] ?? 0;
                $kategori_id = $_POST['kategori_id'] ?? 0;
                $alt_kategori_adi = trim($_POST['alt_kategori_adi'] ?? '');
                $durum = isset($_POST['durum']) ? 1 : 0;
                if (!empty($alt_kategori_adi) && $alt_kategori_id > 0 && $kategori_id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE alt_kategoriler SET kategori_id = :kategori_id, alt_kategori_adi = :adi, durum = :durum WHERE id = :id");
                        $stmt->execute([
                            ':kategori_id' => $kategori_id,
                            ':adi' => $alt_kategori_adi,
                            ':durum' => $durum,
                            ':id' => $alt_kategori_id
                        ]);
                        $successMessage = 'Alt kategori bilgileri güncellendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Alt kategori adı boş olamaz veya geçersiz ID değerleri.';
                }
                break;

            case 'delete_subcategory':
                $alt_kategori_id = $_POST['alt_kategori_id'] ?? 0;
                if ($alt_kategori_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM alt_kategoriler WHERE id = :id");
                        $stmt->execute([':id' => $alt_kategori_id]);
                        $successMessage = 'Alt kategori başarıyla silindi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Silme işlemi sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Geçersiz alt kategori ID.';
                }
                break;

            case 'add_customer_type':
                $tip_adi = trim($_POST['tip_adi'] ?? '');
                if (!empty($tip_adi)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO musteri_tipleri (tip_adi) VALUES (:adi)");
                        $stmt->execute([':adi' => $tip_adi]);
                        $successMessage = 'Müşteri tipi başarıyla eklendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Müşteri tipi eklenirken hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Müşteri tipi adı boş olamaz.';
                }
                break;

            case 'update_customer_type':
                $tip_id = $_POST['tip_id'] ?? 0;
                $tip_adi = trim($_POST['tip_adi'] ?? '');
                $durum = isset($_POST['durum']) ? 1 : 0;
                if (!empty($tip_adi) && $tip_id > 0) {
                    try {
                        $stmt = $pdo->prepare("UPDATE musteri_tipleri SET tip_adi = :adi, durum = :durum WHERE id = :id");
                        $stmt->execute([
                            ':adi' => $tip_adi,
                            ':durum' => $durum,
                            ':id' => $tip_id
                        ]);
                        $successMessage = 'Müşteri tipi bilgileri güncellendi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Güncelleme sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Müşteri tipi adı boş olamaz veya geçersiz müşteri tipi ID.';
                }
                break;

            case 'delete_customer_type':
                $tip_id = $_POST['tip_id'] ?? 0;
                if ($tip_id > 0) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM musteri_tipleri WHERE id = :id");
                        $stmt->execute([':id' => $tip_id]);
                        $successMessage = 'Müşteri tipi başarıyla silindi.';
                    } catch (PDOException $e) {
                        $errorMessage = 'Silme işlemi sırasında hata oluştu: ' . $e->getMessage();
                    }
                } else {
                    $errorMessage = 'Geçersiz müşteri tipi ID.';
                }
                break;
        }
    }
}

// Dinamik içerikler için sorgular
$bankalar   = $pdo->query("SELECT * FROM banka_listesi ORDER BY banka_adi")->fetchAll();
$markalar   = $pdo->query("SELECT * FROM markalar ORDER BY marka_adi")->fetchAll();
$kategoriler = $pdo->query("SELECT * FROM kategoriler ORDER BY kategori_adi")->fetchAll();
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3176FF',
                        secondary: '#6B7280'
                    },
                    borderRadius: {
                        'none': '0px',
                        'sm': '4px',
                        DEFAULT: '8px',
                        'md': '12px',
                        'lg': '16px',
                        'xl': '20px',
                        '2xl': '24px',
                        '3xl': '32px',
                        'full': '9999px',
                        'button': '8px'
                    }
                }
            }
        }
    </script>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Mobil uyumluluk için eklenen stiller */
        html, body {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        /* Sekme navigasyonu için mobil uyumluluk */
        @media (max-width: 768px) {
            nav.flex {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                padding-bottom: 5px;
            }
            
            nav.flex::-webkit-scrollbar {
                height: 3px;
            }
            
            nav.flex::-webkit-scrollbar-track {
                background: #f1f1f1;
            }
            
            nav.flex::-webkit-scrollbar-thumb {
                background: #888;
                border-radius: 3px;
            }
            
            .tab-btn {
                white-space: nowrap;
                flex-shrink: 0;
                font-size: 0.875rem;
                padding: 0.75rem 1rem;
            }
            
            .tab-btn i {
                margin-right: 0.25rem;
            }
        }
        
        /* Tablo ve içerik alanları için mobil uyumluluk */
        .overflow-x-auto {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
            scrollbar-width: thin;
        }
        
        .overflow-x-auto::-webkit-scrollbar {
            height: 3px;
        }
        
        .overflow-x-auto::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .overflow-x-auto::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        /* Responsive grid düzenlemeleri */
        @media (max-width: 640px) {
            .grid {
                grid-template-columns: 1fr !important;
            }
            
            .max-w-7xl {
                padding-left: 1rem;
                padding-right: 1rem;
            }
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-2 sm:px-4 py-4 sm:py-8">
        <!-- Başarı ve Hata Mesajları -->
    <?php if ($successMessage): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

        <div class="bg-white rounded-lg shadow-sm p-3 sm:p-6">
            <!-- Sekme Navigasyonu -->
            <div class="border-b overflow-hidden">
                <nav class="flex space-x-4 sm:space-x-8" role="tablist">
                    <button class="tab-btn active px-3 py-3 sm:px-4 sm:py-4 text-primary border-b-2 border-primary font-medium text-sm sm:text-base" data-tab="user">
                        <i class="ri-user-line mr-1 sm:mr-2"></i>Kullanıcı
                </button>
                    <button class="tab-btn px-3 py-3 sm:px-4 sm:py-4 text-gray-500 hover:text-gray-700 font-medium text-sm sm:text-base" data-tab="sales">
                        <i class="ri-shopping-cart-line mr-1 sm:mr-2"></i>Satış
                    </button>
                    <button class="tab-btn px-3 py-3 sm:px-4 sm:py-4 text-gray-500 hover:text-gray-700 font-medium text-sm sm:text-base" data-tab="customer">
                        <i class="ri-team-line mr-1 sm:mr-2"></i>Müşteri
                    </button>
                    <button class="tab-btn px-3 py-3 sm:px-4 sm:py-4 text-gray-500 hover:text-gray-700 font-medium text-sm sm:text-base" data-tab="brand">
                        <i class="ri-store-line mr-1 sm:mr-2"></i>Marka
                    </button>
                    <button class="tab-btn px-3 py-3 sm:px-4 sm:py-4 text-gray-500 hover:text-gray-700 font-medium text-sm sm:text-base" data-tab="category">
                        <i class="ri-folder-line mr-1 sm:mr-2"></i>Kategori
                    </button>
                    <button class="tab-btn px-3 py-3 sm:px-4 sm:py-4 text-gray-500 hover:text-gray-700 font-medium text-sm sm:text-base" data-tab="bank">
                        <i class="ri-bank-line mr-1 sm:mr-2"></i>Banka
                    </button>
                </nav>
            </div>

            <!-- Sekme İçerikleri -->
            <div class="mt-6">
                <!-- Kullanıcı Sekmesi -->
                <div id="user" class="tab-content active">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 gap-6">
                            <div class="bg-white p-4 sm:p-6 rounded-lg border">
                                <h3 class="text-lg font-medium mb-4">Profil Bilgileri</h3>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Ad Soyad</label>
                                            <input type="text" name="ad_soyad" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($kullanici['kullanici_adi'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">E-posta</label>
                                            <input type="email" name="eposta" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($kullanici['eposta'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Telefon</label>
                                            <input type="tel" name="telefon" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($kullanici['telefon'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Profil Bilgilerini Güncelle</button>
                                    </div>
                                </form>
                            </div>

                            <div class="bg-white p-4 sm:p-6 rounded-lg border">
                                <h3 class="text-lg font-medium mb-4">Şifre Değiştirme</h3>
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Mevcut Şifre</label>
                                            <input type="password" name="mevcut_sifre" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Yeni Şifre</label>
                                            <input type="password" name="yeni_sifre" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Yeni Şifre Tekrar</label>
                                            <input type="password" name="yeni_sifre_tekrar" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Şifreyi Değiştir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Satış Sekmesi -->
                <div id="sales" class="tab-content">
                    <div class="space-y-6">
                        <div class="bg-white p-6 rounded-lg border">
                            <h3 class="text-lg font-medium mb-4">Fiyatlandırma Ayarları</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Para Birimi</label>
                                    <select class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary">
                                        <option>TRY - Türk Lirası</option>
                                        <option>USD - Amerikan Doları</option>
                                        <option>EUR - Euro</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Varsayılan KDV Oranı (%)</label>
                                    <input type="number" class="mt-1 block w-full rounded-button border-gray-300 shadow-sm focus:border-primary focus:ring-primary" value="18">
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end space-x-3">
                            <button class="px-4 py-2 border border-gray-300 rounded-button text-gray-700 hover:bg-gray-50">İptal</button>
                            <button class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Kaydet</button>
                        </div>
                    </div>
                </div>

                <!-- Müşteri Sekmesi -->
                <div id="customer" class="tab-content">
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                            <h3 class="text-lg font-medium">Müşteri Tipleri</h3>
                            <button onclick="openAddCustomerTypeModal()" class="mt-2 sm:mt-0 px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90 flex items-center">
                                <i class="ri-add-line mr-2"></i>Yeni Müşteri Tipi Ekle
                            </button>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Müşteri Tipi</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                        </tr>
                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM musteri_tipleri ORDER BY tip_adi");
                                            $musteri_tipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (count($musteri_tipleri) > 0) {
                                                foreach ($musteri_tipleri as $tip) {
                                                    echo '<tr>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($tip['tip_adi']) . '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm">';
                                                    if ($tip['durum'] == 1) {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>';
                                                    } else {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Pasif</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-right">';
                                                    echo '<button onclick=\'openEditCustomerTypeModal(' . json_encode($tip) . ')\' class="text-blue-600 hover:text-blue-900 mr-3"><i class="ri-edit-line"></i></button>';
                                                    echo '<form method="POST" action="" class="inline-block" onsubmit="return confirm(\'Bu müşteri tipini silmek istediğinizden emin misiniz?\')">';
                                                    echo '<input type="hidden" name="action" value="delete_customer_type">';
                                                    echo '<input type="hidden" name="tip_id" value="' . $tip['id'] . '">';
                                                    echo '<button type="submit" class="text-red-600 hover:text-red-900"><i class="ri-delete-bin-line"></i></button>';
                                                    echo '</form>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-gray-500 text-center">Henüz müşteri tipi kaydı bulunmuyor.</td></tr>';
                                            }
                                        } catch (PDOException $e) {
                                            echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-red-500 text-center">Müşteri tipleri listesi alınırken hata oluştu: ' . $e->getMessage() . '</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Marka Sekmesi -->
                <div id="brand" class="tab-content">
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                            <h3 class="text-lg font-medium">Marka Listesi</h3>
                            <button onclick="openAddBrandModal()" class="mt-2 sm:mt-0 px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90 flex items-center">
                                <i class="ri-add-line mr-2"></i>Yeni Marka Ekle
                                    </button>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Marka Adı</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                        </tr>
                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM markalar ORDER BY marka_adi");
                                            $markalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (count($markalar) > 0) {
                                                foreach ($markalar as $marka) {
                                                    echo '<tr>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($marka['marka_adi']) . '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm">';
                                                    if ($marka['durum'] == 1) {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>';
                                                    } else {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Pasif</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-right">';
                                                    echo '<button onclick=\'openEditBrandModal(' . json_encode($marka) . ')\' class="text-blue-600 hover:text-blue-900 mr-3"><i class="ri-edit-line"></i></button>';
                                                    echo '<form method="POST" action="" class="inline-block" onsubmit="return confirm(\'Bu markayı silmek istediğinizden emin misiniz?\')">';
                                                    echo '<input type="hidden" name="action" value="delete_brand">';
                                                    echo '<input type="hidden" name="marka_id" value="' . $marka['id'] . '">';
                                                    echo '<button type="submit" class="text-red-600 hover:text-red-900"><i class="ri-delete-bin-line"></i></button>';
                                                    echo '</form>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-gray-500 text-center">Henüz marka kaydı bulunmuyor.</td></tr>';
                                            }
                                        } catch (PDOException $e) {
                                            echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-red-500 text-center">Marka listesi alınırken hata oluştu: ' . $e->getMessage() . '</td></tr>';
                                        }
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Kategori Sekmesi -->
                <div id="category" class="tab-content">
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                            <h3 class="text-lg font-medium">Kategori Listesi</h3>
                            <button onclick="openAddCategoryModal()" class="mt-2 sm:mt-0 px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90 flex items-center">
                                <i class="ri-add-line mr-2"></i>Yeni Kategori Ekle
                                    </button>
                        </div>
                        
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori Adı</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                            </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM kategoriler ORDER BY kategori_adi");
                                            $kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (count($kategoriler) > 0) {
                                                foreach ($kategoriler as $kategori) {
                                                    echo '<tr>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($kategori['kategori_adi']) . '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm">';
                                                    if ($kategori['durum'] == 1) {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>';
                                                    } else {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Pasif</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-right">';
                                                    echo '<button onclick="openAddSubcategoryModal(' . $kategori['id'] . ', \'' . htmlspecialchars($kategori['kategori_adi']) . '\')" class="text-green-600 hover:text-green-900 mr-3" title="Alt Kategori Ekle"><i class="ri-add-circle-line"></i></button>';
                                                    echo '<button onclick=\'openEditCategoryModal(' . json_encode($kategori) . ')\' class="text-blue-600 hover:text-blue-900 mr-3" title="Düzenle"><i class="ri-edit-line"></i></button>';
                                                    echo '<form method="POST" action="" class="inline-block" onsubmit="return confirm(\'Bu kategoriyi silmek istediğinizden emin misiniz?\')">';
                                                    echo '<input type="hidden" name="action" value="delete_category">';
                                                    echo '<input type="hidden" name="kategori_id" value="' . $kategori['id'] . '">';
                                                    echo '<button type="submit" class="text-red-600 hover:text-red-900" title="Sil"><i class="ri-delete-bin-line"></i></button>';
                                                    echo '</form>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                    
                                                    // Alt kategorileri getir
                                                    $stmtAlt = $pdo->prepare("SELECT * FROM alt_kategoriler WHERE kategori_id = ? ORDER BY alt_kategori_adi");
                                                    $stmtAlt->execute([$kategori['id']]);
                                                    $altKategoriler = $stmtAlt->fetchAll(PDO::FETCH_ASSOC);
                                                    
                                                    if (count($altKategoriler) > 0) {
                                                        foreach ($altKategoriler as $altKategori) {
                                                            echo '<tr class="bg-gray-50">';
                                                            echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900 pl-8">— ' . htmlspecialchars($altKategori['alt_kategori_adi']) . '</td>';
                                                            echo '<td class="px-4 py-3 whitespace-nowrap text-sm">';
                                                            if ($altKategori['durum'] == 1) {
                                                                echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>';
                                                            } else {
                                                                echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Pasif</span>';
                                                            }
                                                            echo '</td>';
                                                            echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-right">';
                                                            echo '<button onclick=\'openEditSubcategoryModal(' . json_encode($altKategori) . ', ' . $kategori['id'] . ')\' class="text-blue-600 hover:text-blue-900 mr-3" title="Düzenle"><i class="ri-edit-line"></i></button>';
                                                            echo '<form method="POST" action="" class="inline-block" onsubmit="return confirm(\'Bu alt kategoriyi silmek istediğinizden emin misiniz?\')">';
                                                            echo '<input type="hidden" name="action" value="delete_subcategory">';
                                                            echo '<input type="hidden" name="alt_kategori_id" value="' . $altKategori['id'] . '">';
                                                            echo '<button type="submit" class="text-red-600 hover:text-red-900" title="Sil"><i class="ri-delete-bin-line"></i></button>';
                                                            echo '</form>';
                                                            echo '</td>';
                                                            echo '</tr>';
                                                        }
                                                    }
                                                }
                                            } else {
                                                echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-gray-500 text-center">Henüz kategori kaydı bulunmuyor.</td></tr>';
                                            }
                                        } catch (PDOException $e) {
                                            echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-red-500 text-center">Kategori listesi alınırken hata oluştu: ' . $e->getMessage() . '</td></tr>';
                                        }
                                        ?>
                    </tbody>
                </table>
                            </div>
            </div>
        </div>
    </div>

                <!-- Banka Sekmesi -->
                <div id="bank" class="tab-content">
                    <div class="space-y-6">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4">
                            <h3 class="text-lg font-medium">Banka Listesi</h3>
                            <button onclick="openAddBankModal()" class="mt-2 sm:mt-0 px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90 flex items-center">
                                <i class="ri-add-line mr-2"></i>Yeni Banka Ekle
                                    </button>
        </div>
                        
                        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banka Adı</th>
                                            <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                                            <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                            </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM banka_listesi ORDER BY banka_adi");
                                            $bankalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if (count($bankalar) > 0) {
                                                foreach ($bankalar as $banka) {
                                                    echo '<tr>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-gray-900">' . htmlspecialchars($banka['banka_adi']) . '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm">';
                                                    if ($banka['durum'] == 1) {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Aktif</span>';
                                                    } else {
                                                        echo '<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Pasif</span>';
                                                    }
                                                    echo '</td>';
                                                    echo '<td class="px-4 py-3 whitespace-nowrap text-sm text-right">';
                                                    echo '<button onclick=\'openEditBankModal(' . json_encode($banka) . ')\' class="text-blue-600 hover:text-blue-900 mr-3"><i class="ri-edit-line"></i></button>';
                                                    echo '<form method="POST" action="" class="inline-block" onsubmit="return confirm(\'Bu bankayı silmek istediğinizden emin misiniz?\')">';
                                                    echo '<input type="hidden" name="action" value="delete_bank">';
                                                    echo '<input type="hidden" name="banka_id" value="' . $banka['id'] . '">';
                                                    echo '<button type="submit" class="text-red-600 hover:text-red-900"><i class="ri-delete-bin-line"></i></button>';
                                                    echo '</form>';
                                                    echo '</td>';
                                                    echo '</tr>';
                                                }
                                            } else {
                                                echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-gray-500 text-center">Henüz banka kaydı bulunmuyor.</td></tr>';
                                            }
                                        } catch (PDOException $e) {
                                            echo '<tr><td colspan="3" class="px-4 py-3 text-sm text-red-500 text-center">Banka listesi alınırken hata oluştu: ' . $e->getMessage() . '</td></tr>';
                                        }
                                        ?>
                    </tbody>
                </table>
                            </div>
            </div>
        </div>
    </div>
            </div>
    </div>
</div>

    <!-- Modallar (Banka, Marka, Kategori ve Alt Kategori İşlemleri) -->
    <!-- Banka Ekleme Modal -->
<div id="addBankModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Yeni Banka Ekle</h3>
                    <form method="POST">
                    <input type="hidden" name="action" value="add_bank">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Banka Adı</label>
                            <input type="text" name="banka_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeAddBankModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Banka Düzenleme Modal -->
<div id="editBankModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Banka Düzenle</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_bank">
                    <input type="hidden" name="banka_id" id="edit_banka_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Banka Adı</label>
                            <input type="text" name="banka_adi" id="edit_banka_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="durum" id="edit_durum" class="rounded text-primary">
                            <span class="ml-2 text-sm text-gray-600">Aktif</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeEditBankModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
    <!-- Banka Silme Modal -->
<div id="deleteBankModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
        <div class="bg-white rounded-lg shadow-xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Banka Sil</h3>
                    <p class="text-gray-600 mb-4"><span id="delete_bank_name"></span> bankasını silmek istediğinize emin misiniz?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_bank">
                    <input type="hidden" name="banka_id" id="delete_banka_id">
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeDeleteBankModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-button hover:bg-red-700">Sil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

    <!-- Marka Ekleme Modal -->
<div id="addBrandModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Yeni Marka Ekle</h3>
                    <form method="POST">
                    <input type="hidden" name="action" value="add_brand">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Marka Adı</label>
                            <input type="text" name="marka_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeAddBrandModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Marka Düzenleme Modal -->
<div id="editBrandModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Marka Düzenle</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_brand">
                    <input type="hidden" name="marka_id" id="edit_marka_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Marka Adı</label>
                            <input type="text" name="marka_adi" id="edit_marka_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="durum" id="edit_marka_durum" class="rounded text-primary">
                            <span class="ml-2 text-sm text-gray-600">Aktif</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeEditBrandModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Marka Silme Modal -->
<div id="deleteBrandModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md">
        <div class="bg-white rounded-lg shadow-xl">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-4">Marka Sil</h3>
                    <p class="text-gray-600 mb-4"><span id="delete_brand_name"></span> markasını silmek istediğinize emin misiniz?</p>
                <form method="POST">
                    <input type="hidden" name="action" value="delete_brand">
                    <input type="hidden" name="marka_id" id="delete_marka_id">
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeDeleteBrandModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-button hover:bg-red-700">Sil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
    <!-- Kategori Ekleme Modal -->
<div id="addCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Yeni Kategori Ekle</h3>
                    <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Adı</label>
                            <input type="text" name="kategori_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
        </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeAddCategoryModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Kaydet</button>
                        </div>
                </form>
                        </div>
                    </div>
                                    </div>
                                    </div>
    <!-- Kategori Düzenleme Modal -->
    <div id="editCategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                    <h3 class="text-lg font-semibold mb-4">Kategori Düzenle</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="update_category">
                        <input type="hidden" name="kategori_id" id="edit_kategori_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Kategori Adı</label>
                            <input type="text" name="kategori_adi" id="edit_kategori_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="mb-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="durum" id="edit_kategori_durum" class="rounded text-primary">
                                <span class="ml-2 text-sm text-gray-600">Aktif</span>
                            </label>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeEditCategoryModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Alt Kategori Ekleme Modal -->
<div id="addSubcategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Yeni Alt Kategori Ekle</h3>
                <form method="POST" id="addSubcategoryForm">
                    <input type="hidden" name="action" value="add_subcategory">
                    <input type="hidden" name="kategori_id" id="add_subcategory_kategori_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Üst Kategori</label>
                            <input type="text" id="add_subcategory_kategori_adi" class="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md" readonly>
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alt Kategori Adı</label>
                            <input type="text" name="alt_kategori_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeAddSubcategoryModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- Alt Kategori Düzenleme Modal -->
<div id="editSubcategoryModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
                <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Alt Kategori Düzenle</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_subcategory">
                    <input type="hidden" name="alt_kategori_id" id="edit_alt_kategori_id">
                    <input type="hidden" name="kategori_id" id="edit_subcategory_kategori_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Alt Kategori Adı</label>
                            <input type="text" name="alt_kategori_adi" id="edit_alt_kategori_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="durum" id="edit_alt_kategori_durum" class="rounded text-primary">
                            <span class="ml-2 text-sm text-gray-600">Aktif</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-4">
                            <button type="button" onclick="closeEditSubcategoryModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Müşteri Tipi Ekleme Modal -->
<div id="addCustomerTypeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Yeni Müşteri Tipi Ekle</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="add_customer_type">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Müşteri Tipi Adı</label>
                        <input type="text" name="tip_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" onclick="closeAddCustomerTypeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Müşteri Tipi Düzenleme Modal -->
<div id="editCustomerTypeModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-md px-4 sm:px-0">
        <div class="bg-white rounded-lg shadow-xl">
            <div class="p-4 sm:p-6">
                <h3 class="text-lg font-semibold mb-4">Müşteri Tipi Düzenle</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="update_customer_type">
                    <input type="hidden" name="tip_id" id="edit_tip_id">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Müşteri Tipi Adı</label>
                        <input type="text" name="tip_adi" id="edit_tip_adi" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                    </div>
                    <div class="mb-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="durum" id="edit_tip_durum" class="rounded text-primary">
                            <span class="ml-2 text-sm text-gray-600">Aktif</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-4">
                        <button type="button" onclick="closeEditCustomerTypeModal()" class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-button">İptal</button>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-primary/90">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
        // Sekme değiştirme işlemi
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabId = button.dataset.tab;
                tabButtons.forEach(btn => btn.classList.remove('active', 'text-primary', 'border-primary'));
                tabContents.forEach(content => content.classList.remove('active'));
                button.classList.add('active', 'text-primary', 'border-primary');
                document.getElementById(tabId).classList.add('active');
            });
        });

        // Modal Fonksiyonları
function openAddBankModal() {
    document.getElementById('addBankModal').classList.remove('hidden');
}
function closeAddBankModal() {
    document.getElementById('addBankModal').classList.add('hidden');
}
function openEditBankModal(banka) {
    document.getElementById('edit_banka_id').value = banka.id;
    document.getElementById('edit_banka_adi').value = banka.banka_adi;
    document.getElementById('edit_durum').checked = banka.durum == 1;
    document.getElementById('editBankModal').classList.remove('hidden');
}
function closeEditBankModal() {
    document.getElementById('editBankModal').classList.add('hidden');
}
function confirmDeleteBank(id, name) {
    document.getElementById('delete_banka_id').value = id;
    document.getElementById('delete_bank_name').textContent = name;
    document.getElementById('deleteBankModal').classList.remove('hidden');
}
function closeDeleteBankModal() {
    document.getElementById('deleteBankModal').classList.add('hidden');
}

function openAddBrandModal() {
    document.getElementById('addBrandModal').classList.remove('hidden');
}
function closeAddBrandModal() {
    document.getElementById('addBrandModal').classList.add('hidden');
}
function openEditBrandModal(marka) {
    document.getElementById('edit_marka_id').value = marka.id;
    document.getElementById('edit_marka_adi').value = marka.marka_adi;
    document.getElementById('edit_marka_durum').checked = marka.durum == 1;
    document.getElementById('editBrandModal').classList.remove('hidden');
}
function closeEditBrandModal() {
    document.getElementById('editBrandModal').classList.add('hidden');
}
function confirmDeleteBrand(id, name) {
    document.getElementById('delete_marka_id').value = id;
    document.getElementById('delete_brand_name').textContent = name;
    document.getElementById('deleteBrandModal').classList.remove('hidden');
}
function closeDeleteBrandModal() {
    document.getElementById('deleteBrandModal').classList.add('hidden');
}

function openAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.remove('hidden');
}
function closeAddCategoryModal() {
    document.getElementById('addCategoryModal').classList.add('hidden');
}
function openEditCategoryModal(kategori) {
    document.getElementById('edit_kategori_id').value = kategori.id;
    document.getElementById('edit_kategori_adi').value = kategori.kategori_adi;
    document.getElementById('edit_kategori_durum').checked = kategori.durum == 1;
    document.getElementById('editCategoryModal').classList.remove('hidden');
}
function closeEditCategoryModal() {
    document.getElementById('editCategoryModal').classList.add('hidden');
}
        function openAddSubcategoryModal(kategoriId, kategoriAdi) {
            document.getElementById('add_subcategory_kategori_id').value = kategoriId;
            document.getElementById('add_subcategory_kategori_adi').value = kategoriAdi;
            document.getElementById('addSubcategoryModal').classList.remove('hidden');
        }
        function closeAddSubcategoryModal() {
            document.getElementById('addSubcategoryModal').classList.add('hidden');
        }
function openEditSubcategoryModal(altKategori, kategoriId) {
    document.getElementById('edit_alt_kategori_id').value = altKategori.id;
    document.getElementById('edit_subcategory_kategori_id').value = kategoriId;
    document.getElementById('edit_alt_kategori_adi').value = altKategori.alt_kategori_adi;
    document.getElementById('edit_alt_kategori_durum').checked = altKategori.durum == 1;
    document.getElementById('editSubcategoryModal').classList.remove('hidden');
}
function closeEditSubcategoryModal() {
    document.getElementById('editSubcategoryModal').classList.add('hidden');
}
        function confirmDeleteSubcategory(id, name) {
            if (confirm(name + ' alt kategorisini silmek istediğinize emin misiniz?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        form.innerHTML = `
                    <input type="hidden" name="action" value="delete_subcategory">
                    <input type="hidden" name="alt_kategori_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
        function confirmDeleteCategory(id, name) {
            if (confirm(name + ' kategorisini silmek istediğinize emin misiniz? Alt kategorileri de silinecektir.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = window.location.href;
        form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="kategori_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Müşteri Tipi Modal Fonksiyonları
function openAddCustomerTypeModal() {
    document.getElementById('addCustomerTypeModal').classList.remove('hidden');
}

function closeAddCustomerTypeModal() {
    document.getElementById('addCustomerTypeModal').classList.add('hidden');
}

function openEditCustomerTypeModal(tip) {
    document.getElementById('edit_tip_id').value = tip.id;
    document.getElementById('edit_tip_adi').value = tip.tip_adi;
    document.getElementById('edit_tip_durum').checked = tip.durum == 1;
    document.getElementById('editCustomerTypeModal').classList.remove('hidden');
}

function closeEditCustomerTypeModal() {
    document.getElementById('editCustomerTypeModal').classList.add('hidden');
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>

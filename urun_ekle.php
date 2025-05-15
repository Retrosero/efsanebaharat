<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Create images directory if it doesn't exist
$uploadDir = 'uploads/images/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Benzersiz ürün kodu oluştur
function generateUniqueProductCode($pdo) {
    $prefix = "URN";
    $unique = false;
    $code = "";
    
    while (!$unique) {
        // 6 haneli rastgele sayı oluştur
        $randomNumber = mt_rand(100000, 999999);
        $code = $prefix . $randomNumber;
        
        // Kodun benzersiz olup olmadığını kontrol et
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM urunler WHERE urun_kodu = :code");
        $stmt->execute([':code' => $code]);
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            $unique = true;
        }
    }
    
    return $code;
}

// Otomatik ürün kodu oluştur
$urun_kodu = generateUniqueProductCode($pdo);

// Form gönderildi mi kontrol et
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST verilerini logla
    error_log("POST verileri: " . print_r($_POST, true));
    error_log("FILES verileri: " . print_r($_FILES, true));
    
    // Form verilerini al
    $urun_adi = trim($_POST['urun_adi'] ?? '');
    $urun_kodu = trim($_POST['urun_kodu'] ?? '');
    $barkod = trim($_POST['barkod'] ?? '');
    $raf_no = trim($_POST['raf_no'] ?? '');
    $ambalaj = trim($_POST['ambalaj'] ?? '');
    $koli_adeti = intval($_POST['koli_adeti'] ?? 1);
    $marka = trim($_POST['marka'] ?? '');
    $kategori = trim($_POST['kategori'] ?? '');
    $birim_fiyat = floatval($_POST['birim_fiyat'] ?? 0);
    $aciklama = trim($_POST['aciklama'] ?? '');
    $kategori_id = intval($_POST['kategori_id'] ?? 0);
    $alt_kategori_id = intval($_POST['alt_kategori_id'] ?? 0);
    $olcum_birimi = trim($_POST['olcum_birimi'] ?? 'adet');
    
    // Validasyon
    if (empty($urun_adi)) {
        $errorMessage = 'Ürün adı boş olamaz.';
    } elseif (empty($urun_kodu)) {
        $errorMessage = 'Ürün kodu boş olamaz.';
    } elseif (empty($birim_fiyat)) {
        $errorMessage = 'Birim fiyat boş olamaz.';
    } else {
        // Ürün kodunun benzersiz olup olmadığını kontrol et
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM urunler WHERE urun_kodu = :code");
        $stmtCheck->execute([':code' => $urun_kodu]);
        $codeExists = $stmtCheck->fetchColumn();
        
        if ($codeExists > 0) {
            $errorMessage = 'Bu ürün kodu zaten kullanılıyor. Lütfen başka bir kod girin veya otomatik oluşturulan kodu kullanın.';
            // Yeni bir benzersiz kod oluştur
            $urun_kodu = generateUniqueProductCode($pdo);
        } else {
            // Yardımcı fonksiyon: $_FILES dizisini yeniden düzenle
            function reArrayFiles($files) {
                $file_array = array();
                for ($i = 0; $i < count($files['name']); $i++) {
                    $file_array[$i]['name'] = $files['name'][$i];
                    $file_array[$i]['type'] = $files['type'][$i];
                    $file_array[$i]['tmp_name'] = $files['tmp_name'][$i];
                    $file_array[$i]['error'] = $files['error'][$i];
                    $file_array[$i]['size'] = $files['size'][$i];
                }
                return $file_array;
            }

            // Resimleri işle
            $resim_urls = array_fill(0, 10, null); // 10 elemanlı boş dizi
            
            if (isset($_FILES['urun_resimler'])) {
                $files = reArrayFiles($_FILES['urun_resimler']);
                
                foreach ($files as $index => $file) {
                    if ($file['error'] == 0 && $index < 10) {
                        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
                        $filename = $file['name'];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        if (in_array($ext, $allowed)) {
                            $newFilename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                            $uploadPath = $uploadDir . $newFilename;
                            
                            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                                $resim_urls[$index] = $uploadPath;
                            }
                        }
                    }
                }
            }

            try {
                // Veritabanı tablosunda raf_no ve koli_adeti sütunları olup olmadığını kontrol et
                $checkColumns = $pdo->query("SHOW COLUMNS FROM urunler LIKE 'raf_no'");
                $hasRafNo = $checkColumns->rowCount() > 0;
                
                $checkColumns = $pdo->query("SHOW COLUMNS FROM urunler LIKE 'koli_adeti'");
                $hasKoliAdeti = $checkColumns->rowCount() > 0;
                
                $checkColumns = $pdo->query("SHOW COLUMNS FROM urunler LIKE 'ambalaj'");
                $hasAmbalaj = $checkColumns->rowCount() > 0;
                
                $checkColumns = $pdo->query("SHOW COLUMNS FROM urunler LIKE 'olcum_birimi'");
                $hasOlcumBirimi = $checkColumns->rowCount() > 0;
                
                // Eğer sütunlar yoksa, bunları ekle
                if (!$hasRafNo) {
                    $pdo->exec("ALTER TABLE urunler ADD COLUMN raf_no VARCHAR(50) DEFAULT NULL AFTER barkod");
                    error_log("raf_no sütunu eklendi");
                }
                
                if (!$hasKoliAdeti) {
                    $pdo->exec("ALTER TABLE urunler ADD COLUMN koli_adeti INT DEFAULT 1 AFTER raf_no");
                    error_log("koli_adeti sütunu eklendi");
                }
                
                if (!$hasAmbalaj) {
                    $pdo->exec("ALTER TABLE urunler ADD COLUMN ambalaj VARCHAR(50) DEFAULT NULL AFTER koli_adeti");
                    error_log("ambalaj sütunu eklendi");
                }
                
                if (!$hasOlcumBirimi) {
                    $pdo->exec("ALTER TABLE urunler ADD COLUMN olcum_birimi enum('adet','kg','gr') NOT NULL DEFAULT 'adet' AFTER birim");
                    error_log("olcum_birimi sütunu eklendi");
                }
                
                if (!$hasRafNo || !$hasKoliAdeti || !$hasAmbalaj || !$hasOlcumBirimi) {
                    error_log("Tabloya yeni sütunlar eklendi, işlem devam ediyor");
                }
                
                // SQL sorgusunu güncelle
                $sql = "
                    INSERT INTO urunler (
                        urun_adi, urun_kodu, barkod";
                
                if ($hasRafNo) $sql .= ", raf_no";
                if ($hasKoliAdeti) $sql .= ", koli_adeti";
                if ($hasAmbalaj) $sql .= ", ambalaj";
                if ($hasOlcumBirimi) $sql .= ", olcum_birimi";
                
                $sql .= ", marka_id, 
                        kategori_id, alt_kategori_id,
                        birim_fiyat, aciklama, 
                        resim_url, resim_url_2, resim_url_3, resim_url_4, resim_url_5,
                        resim_url_6, resim_url_7, resim_url_8, resim_url_9, resim_url_10,
                        aktif, created_at
                    ) VALUES (
                        :urun_adi, :urun_kodu, :barkod";
                
                if ($hasRafNo) $sql .= ", :raf_no";
                if ($hasKoliAdeti) $sql .= ", :koli_adeti";
                if ($hasAmbalaj) $sql .= ", :ambalaj";
                if ($hasOlcumBirimi) $sql .= ", :olcum_birimi";
                
                $sql .= ", :marka_id,
                        :kategori_id, :alt_kategori_id,
                        :birim_fiyat, :aciklama,
                        :resim_url, :resim_url_2, :resim_url_3, :resim_url_4, :resim_url_5,
                        :resim_url_6, :resim_url_7, :resim_url_8, :resim_url_9, :resim_url_10,
                        1, NOW()
                    )";
                
                error_log("SQL Sorgusu: " . $sql);
                
                $stmt = $pdo->prepare($sql);
                
                // Parametreleri hazırla
                $params = [
                    ':urun_adi' => $urun_adi,
                    ':urun_kodu' => $urun_kodu,
                    ':barkod' => $barkod,
                    ':marka_id' => empty($marka) ? null : $marka,
                    ':kategori_id' => empty($kategori_id) ? null : $kategori_id,
                    ':alt_kategori_id' => empty($alt_kategori_id) ? null : $alt_kategori_id,
                    ':birim_fiyat' => $birim_fiyat,
                    ':aciklama' => $aciklama
                ];
                
                // Raf no ve koli adeti parametrelerini ekle
                if ($hasRafNo) {
                    $params[':raf_no'] = $raf_no;
                }
                
                if ($hasKoliAdeti) {
                    $params[':koli_adeti'] = $koli_adeti;
                }
                
                // Ambalaj parametresini ekle
                if ($hasAmbalaj) {
                    $params[':ambalaj'] = $ambalaj;
                }
                
                // Ölçüm birimi parametresini ekle
                if ($hasOlcumBirimi) {
                    $params[':olcum_birimi'] = $olcum_birimi;
                }
                
                // Resim URL'lerini parametrelere ekle
                for ($i = 0; $i < 10; $i++) {
                    $params[':resim_url'.($i > 0 ? '_'.($i+1) : '')] = $resim_urls[$i];
                }
                
                // Hata ayıklama için parametreleri logla
                error_log("Ürün ekleme parametreleri: " . print_r($params, true));
                
                $stmt->execute($params);
                $lastInsertId = $pdo->lastInsertId();
                error_log("Ürün başarıyla eklendi. ID: " . $lastInsertId);
                $successMessage = 'Ürün başarıyla kaydedildi. (ID: ' . $lastInsertId . ')';
                
                // Formu temizle ve yeni ürün kodu oluştur
                $urun_adi = $barkod = $raf_no = $aciklama = '';
                $urun_kodu = generateUniqueProductCode($pdo);
                
            } catch (Exception $e) {
                error_log("Ürün ekleme hatası: " . $e->getMessage());
                
                if ($e->getCode() == 23000 && strpos($e->getMessage(), 'idx_urun_kodu')) {
                    $errorMessage = 'Bu ürün kodu zaten kullanılıyor. Lütfen başka bir kod girin veya otomatik oluşturulan kodu kullanın.';
                    // Yeni bir benzersiz kod oluştur
                    $urun_kodu = generateUniqueProductCode($pdo);
                } else {
                    $errorMessage = "Veritabanı hatası: " . $e->getMessage();
                }
            }
        }
    }
}

// Markaları veritabanından çek
try {
    $stmt = $pdo->query("SELECT * FROM markalar ORDER BY marka_adi");
    $markalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $markalar = [];
}

// Ambalaj tiplerini veritabanından çek
try {
    $stmt = $pdo->query("SELECT * FROM ambalaj_tipleri ORDER BY ambalaj_adi");
    $ambalajlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $ambalajlar = [];
}

// Kategorileri ve alt kategorileri çek
try {
    $stmt = $pdo->query("SELECT * FROM kategoriler WHERE durum = 1 ORDER BY kategori_adi");
    $kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Her kategori için alt kategorileri çek
    foreach ($kategoriler as &$kategori) {
        $stmt = $pdo->prepare("SELECT * FROM alt_kategoriler WHERE kategori_id = ? AND durum = 1 ORDER BY alt_kategori_adi");
        $stmt->execute([$kategori['id']]);
        $kategori['alt_kategoriler'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $kategoriler = [];
}
?>

<div class="p-4">
    <!-- Geri Butonu -->
    <button
        onclick="history.back()"
        class="mb-4 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm flex items-center"
    >
        <i class="ri-arrow-left-line mr-2"></i> Geri
    </button>

    <?php if ($successMessage): ?>
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm p-6">
<h1 class="text-2xl font-semibold text-gray-800 mb-6">Yeni Ürün Ekle</h1>
<div class="h-px bg-gray-200 mb-8"></div>
        
        <form id="productForm" method="POST" class="space-y-6" enctype="multipart/form-data">
<div class="grid gap-6 sm:grid-cols-2">
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Ürün Adı <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        name="urun_adi"
                        required 
                        value="<?= htmlspecialchars($urun_adi ?? '') ?>"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                        placeholder="Ürün adını giriniz"
                    >
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Ürün Kodu <span class="text-red-500">*</span></label>
                    <input 
                        type="text" 
                        name="urun_kodu"
                        required 
                        value="<?= htmlspecialchars($urun_kodu) ?>"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                        placeholder="Ürün kodunu giriniz"
                    >
                    <p class="text-xs text-gray-500 mt-1">Benzersiz ürün kodu otomatik oluşturulur. İsterseniz değiştirebilirsiniz.</p>
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Barkod</label>
                    <input 
                        type="text" 
                        name="barkod"
                        value="<?= htmlspecialchars($barkod ?? '') ?>"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                        placeholder="Barkod numarasını giriniz"
                    >
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Raf No</label>
                    <input 
                        type="text" 
                        name="raf_no"
                        value="<?= htmlspecialchars($raf_no ?? '') ?>"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                        placeholder="Raf numarasını giriniz"
                    >
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Ambalaj</label>
                    <select 
                        name="ambalaj"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm"
                    >
                        <option value="">Ambalaj seçiniz</option>
                        <?php foreach ($ambalajlar as $a): ?>
                            <option value="<?= $a['id'] ?>" <?= ($ambalaj == $a['id']) ? 'selected' : '' ?>><?= htmlspecialchars($a['ambalaj_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Ölçüm Birimi</label>
                    <select 
                        name="olcum_birimi"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm"
                    >
                        <option value="adet" <?= ($olcum_birimi == 'adet') ? 'selected' : '' ?>>Adet</option>
                        <option value="kg" <?= ($olcum_birimi == 'kg') ? 'selected' : '' ?>>Kilogram (kg)</option>
                        <option value="gr" <?= ($olcum_birimi == 'gr') ? 'selected' : '' ?>>Gram (gr)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Ürünün satış şeklini belirler. Kilogram veya gram seçilirse, satış ekranında ağırlık girişi yapılabilir.</p>
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Koli Adeti</label>
                    <input 
                        type="number" 
                        name="koli_adeti"
                        min="1"
                        value="<?= htmlspecialchars($koli_adeti ?? 1) ?>"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                        placeholder="Koli adetini giriniz"
                    >
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Marka</label>
                    <select 
                        name="marka"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm"
                    >
                        <option value="">Marka seçiniz</option>
                        <?php foreach ($markalar as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($marka == $m['id']) ? 'selected' : '' ?>><?= htmlspecialchars($m['marka_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
</div>
                
<div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Kategori</label>
                    <select 
                        name="kategori_id"
                        id="kategori_select"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm"
                    >
                        <option value="">Ana kategori seçiniz</option>
                        <?php foreach ($kategoriler as $k): ?>
                            <option value="<?= $k['id'] ?>" <?= ($kategori_id == $k['id']) ? 'selected' : '' ?>><?= htmlspecialchars($k['kategori_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
</div>

<div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Alt Kategori</label>
                    <select 
                        name="alt_kategori_id"
                        id="alt_kategori_select"
                        class="w-full h-11 px-4 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm"
                        <?= empty($kategori_id) ? 'disabled' : '' ?>
                    >
                        <option value="">Önce ana kategori seçiniz</option>
                        <?php if (!empty($kategori_id)): ?>
                            <?php foreach ($kategoriler as $k): ?>
                                <?php if ($k['id'] == $kategori_id && isset($k['alt_kategoriler'])): ?>
                                    <?php foreach ($k['alt_kategoriler'] as $alt): ?>
                                        <option value="<?= $alt['id'] ?>" <?= ($alt_kategori_id == $alt['id']) ? 'selected' : '' ?>><?= htmlspecialchars($alt['alt_kategori_adi']) ?></option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
</div>
                
<div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Birim Fiyat <span class="text-red-500">*</span></label>
<div class="relative">
                        <input 
                            type="number" 
                            name="birim_fiyat"
                            required 
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($birim_fiyat ?? 0) ?>"
                            class="w-full h-11 pl-4 pr-12 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                            placeholder="0.00"
                        >
<span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500">₺</span>
</div>
</div>

<div class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700">Ürün Resimleri (En fazla 10 adet)</label>
                    <div 
                        id="dropzone" 
                        class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary hover:bg-primary/5 transition-colors cursor-pointer"
                    >
                        <input 
                            type="file" 
                            id="fileInput" 
                            name="urun_resimler[]" 
                            multiple 
                            accept=".jpg,.jpeg,.png,.webp" 
                            class="hidden"
                        >
                        <div class="space-y-3">
                            <i class="ri-upload-cloud-2-line text-4xl text-gray-400"></i>
                            <div class="text-gray-600">
                                <p class="font-medium">Resimleri sürükleyip bırakın</p>
                                <p class="text-sm text-gray-500">veya <span class="text-primary">dosya seçmek için tıklayın</span></p>
                                <p class="text-xs text-gray-400 mt-2">PNG, JPG, JPEG veya WEBP (En fazla 10 resim, her biri max. 5MB)</p>
</div>
</div>
</div>
                    
                    <!-- Resim Önizleme Alanı -->
                    <div id="imagePreviewContainer" class="hidden mt-4">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Yüklenen Resimler</h4>
                        <div id="imageList" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4"></div>
</div>
</div>
</div>
            
            <div class="space-y-2">
<label class="block text-sm font-medium text-gray-700">Açıklama</label>
                <textarea 
                    name="aciklama"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-200 rounded focus:border-primary focus:ring-1 focus:ring-primary outline-none text-sm" 
                    placeholder="Ürün hakkında açıklama giriniz"
                ><?= htmlspecialchars($aciklama ?? '') ?></textarea>
</div>
            
<div class="flex justify-end pt-6">
                <button 
                    type="submit" 
                    class="w-full sm:w-auto px-6 py-3 bg-primary text-white rounded-button hover:bg-primary/90 transition-colors whitespace-nowrap"
                >
                    Ürünü Kaydet
                </button>
</div>
</form>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Kategori seçildiğinde alt kategorileri yükle
    const kategoriSelect = document.getElementById('kategori_select');
    const altKategoriSelect = document.getElementById('alt_kategori_select');
    
    if (kategoriSelect.value) {
        // Zaten alt kategoriler PHP tarafında yüklendi, sadece disabled durumunu kaldır
        altKategoriSelect.disabled = false;
    }
    
    // Kategori değiştiğinde alt kategorileri yükle
    kategoriSelect.addEventListener('change', function() {
        const kategoriId = this.value;
        altKategoriSelect.innerHTML = '<option value="">Alt kategori seçiniz</option>';
        
        if (!kategoriId) {
            altKategoriSelect.disabled = true;
            return;
        }
        
        // Seçilen kategoriye ait alt kategorileri bul
        const kategoriler = <?= json_encode($kategoriler) ?>;
        const secilenKategori = kategoriler.find(k => k.id == kategoriId);
        
        if (secilenKategori && secilenKategori.alt_kategoriler && secilenKategori.alt_kategoriler.length > 0) {
            secilenKategori.alt_kategoriler.forEach(alt => {
                const option = document.createElement('option');
                option.value = alt.id;
                option.textContent = alt.alt_kategori_adi;
                altKategoriSelect.appendChild(option);
            });
            
            altKategoriSelect.disabled = false;
        } else {
            altKategoriSelect.disabled = true;
        }
    });

    // Resim yükleme işlemleri
    const dropzone = document.getElementById('dropzone');
    const fileInput = document.getElementById('fileInput');
    const imageList = document.getElementById('imageList');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    let files = [];

    // Dropzone click olayı
    dropzone.addEventListener('click', () => fileInput.click());

    // Drag & Drop olayları
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Drag efektleri
    ['dragenter', 'dragover'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.add('border-primary', 'bg-primary/5');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropzone.addEventListener(eventName, () => {
            dropzone.classList.remove('border-primary', 'bg-primary/5');
        });
    });

    // Dosya bırakma olayı
    dropzone.addEventListener('drop', (e) => {
        const droppedFiles = e.dataTransfer.files;
        handleFiles(droppedFiles);
    });

    // Dosya input değişikliği
    fileInput.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    function handleFiles(newFiles) {
        if (files.length + newFiles.length > 10) {
            alert('En fazla 10 resim yükleyebilirsiniz!');
            return;
        }

        Array.from(newFiles).forEach(file => {
            if (!file.type.match('image.*')) {
                alert('Lütfen sadece resim dosyası yükleyin!');
                return;
            }
            
            // Dosya boyutu kontrolü (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('Dosya boyutu 5MB\'dan küçük olmalıdır!');
                return;
            }
            
            files.push(file);
        });

        updatePreview();
    }

    function updatePreview() {
        if (files.length > 0) {
            imagePreviewContainer.classList.remove('hidden');
        } else {
            imagePreviewContainer.classList.add('hidden');
        }

        imageList.innerHTML = files.map((file, index) => `
            <div class="relative group" data-index="${index}">
                <div class="aspect-square w-full rounded-lg border border-gray-200 overflow-hidden">
                    <img 
                        src="${URL.createObjectURL(file)}" 
                        class="w-full h-full object-contain bg-gray-50"
                        alt="${file.name}"
                    >
                </div>
                <div class="absolute inset-0 bg-black bg-opacity-50 opacity-0 group-hover:opacity-100 transition-opacity rounded-lg flex items-center justify-center space-x-2">
                    <button 
                        type="button"
                        onclick="moveImage(${index}, -1)" 
                        class="p-2 bg-white rounded-full text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        ${index === 0 ? 'disabled' : ''}
                    >
                        <i class="ri-arrow-left-line"></i>
                    </button>
                    <button 
                        type="button"
                        onclick="deleteImage(${index})" 
                        class="p-2 bg-white rounded-full text-red-600 hover:bg-red-50"
                    >
                        <i class="ri-delete-bin-line"></i>
                    </button>
                    <button 
                        type="button"
                        onclick="moveImage(${index}, 1)" 
                        class="p-2 bg-white rounded-full text-gray-800 hover:bg-gray-100 disabled:opacity-50 disabled:cursor-not-allowed"
                        ${index === files.length - 1 ? 'disabled' : ''}
                    >
                        <i class="ri-arrow-right-line"></i>
                    </button>
                </div>
                ${index === 0 ? '<div class="absolute top-2 left-2 bg-primary text-white text-xs px-2 py-1 rounded">Ana Resim</div>' : ''}
            </div>
        `).join('');
    }

    // Global fonksiyonları tanımla
    window.deleteImage = function(index) {
        files.splice(index, 1);
        updatePreview();
    };

    window.moveImage = function(index, direction) {
        const newIndex = index + direction;
        if (newIndex >= 0 && newIndex < files.length) {
            [files[index], files[newIndex]] = [files[newIndex], files[index]];
            updatePreview();
        }
    };

    // Form submit öncesi kontrol
    document.getElementById('productForm').addEventListener('submit', function(e) {
        e.preventDefault(); // Form submit'i durdur
        
        if (files.length === 0) {
            if (!confirm('Hiç resim yüklemediniz. Devam etmek istiyor musunuz?')) {
                return;
            }
        }

        // FormData oluştur
        const formData = new FormData(this);
        
        // Mevcut dosyaları FormData'ya ekle
        files.forEach((file, index) => {
            formData.append('urun_resimler[]', file);
        });

        // Form gönder
        fetch(this.action, {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            // Başarılı ise sayfayı yenile veya yönlendir
            window.location.reload();
        })
        .catch(error => {
            console.error('Hata:', error);
            alert('Bir hata oluştu. Lütfen tekrar deneyin.');
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
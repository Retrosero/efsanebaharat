<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
include 'includes/header.php';

// Ürün ID'sini al
$urun_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ürün bilgilerini çek
try {
    $stmt = $pdo->prepare("
        SELECT u.*, m.marka_adi, k.kategori_adi 
        FROM urunler u
        LEFT JOIN markalar m ON u.marka_id = m.id
        LEFT JOIN kategoriler k ON u.kategori_id = k.id
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $urun_id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$urun) {
        $_SESSION['hata_mesaji'] = "Ürün bulunamadı.";
        header('Location: urunler.php');
        exit;
    }
    
    // Markaları çek
    $stmt = $pdo->query("SELECT id, marka_adi FROM markalar WHERE durum = 1 ORDER BY marka_adi");
    $markalar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kategorileri çek
    $stmt = $pdo->query("SELECT id, kategori_adi FROM kategoriler WHERE durum = 1 ORDER BY kategori_adi");
    $kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $_SESSION['hata_mesaji'] = "Veritabanı hatası: " . $e->getMessage();
    header('Location: urunler.php');
    exit;
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Form verilerini al
        $urun_adi = trim($_POST['urun_adi']);
        $urun_kodu = trim($_POST['urun_kodu'] ?? '');
        $barkod = trim($_POST['barkod'] ?? '');
        $marka_id = intval($_POST['marka_id'] ?? 0);
        $kategori_id = intval($_POST['kategori_id'] ?? 0);
        $birim_fiyat = floatval(str_replace(',', '.', $_POST['birim_fiyat']));
        $kdv_orani = intval($_POST['kdv_orani']);
        $stok_miktari = floatval(str_replace(',', '.', $_POST['stok_miktari']));
        $minimum_stok = floatval(str_replace(',', '.', $_POST['minimum_stok'] ?? 0));
        $raf_no = trim($_POST['raf_no'] ?? '');
        $birim = trim($_POST['birim'] ?? 'Adet');
        $ambalaj = trim($_POST['ambalaj'] ?? '');
        $koli_adeti = intval($_POST['koli_adeti'] ?? 1);
        $aciklama = trim($_POST['aciklama'] ?? '');
        $durum = isset($_POST['durum']) ? 1 : 0;
        
        // Ürünü güncelle
        $stmt = $pdo->prepare("
            UPDATE urunler SET 
                urun_adi = :urun_adi,
                urun_kodu = :urun_kodu,
                barkod = :barkod,
                marka_id = :marka_id,
                kategori_id = :kategori_id,
                birim_fiyat = :birim_fiyat,
                kdv_orani = :kdv_orani,
                stok_miktari = :stok_miktari,
                minimum_stok = :minimum_stok,
                raf_no = :raf_no,
                birim = :birim,
                ambalaj = :ambalaj,
                koli_adeti = :koli_adeti,
                aciklama = :aciklama,
                aktif = :durum,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':urun_adi' => $urun_adi,
            ':urun_kodu' => $urun_kodu,
            ':barkod' => $barkod,
            ':marka_id' => $marka_id ?: null,
            ':kategori_id' => $kategori_id ?: null,
            ':birim_fiyat' => $birim_fiyat,
            ':kdv_orani' => $kdv_orani,
            ':stok_miktari' => $stok_miktari,
            ':minimum_stok' => $minimum_stok,
            ':raf_no' => $raf_no,
            ':birim' => $birim,
            ':ambalaj' => $ambalaj,
            ':koli_adeti' => $koli_adeti,
            ':aciklama' => $aciklama,
            ':durum' => $durum,
            ':id' => $urun_id
        ]);
        
        // Resim yükleme işlemi
        $hedef_klasor = "uploads/products/";
        if (!file_exists($hedef_klasor)) {
            mkdir($hedef_klasor, 0777, true);
        }

        // İlk resmi resim_url alanına kaydet
        if (!empty($_FILES['resim_1']['name'])) {
            $dosya_adi = uniqid() . "_" . basename($_FILES['resim_1']["name"]);
            $hedef_dosya = $hedef_klasor . $dosya_adi;
            
            if (move_uploaded_file($_FILES['resim_1']["tmp_name"], $hedef_dosya)) {
                // Eski resmi sil
                if (!empty($urun['resim_url'])) {
                    @unlink($urun['resim_url']);
                }
                
                // Yeni resim yolunu kaydet
                $stmt = $pdo->prepare("UPDATE urunler SET resim_url = :resim_url WHERE id = :id");
                $stmt->execute([
                    ':resim_url' => $hedef_dosya,
                    ':id' => $urun_id
                ]);
            }
        }

        // Diğer resimleri resim_url_1 den başlayarak kaydet
        for($i = 2; $i <= 10; $i++) {
            if (!empty($_FILES['resim_' . $i]['name'])) {
                $dosya_adi = uniqid() . "_" . basename($_FILES['resim_' . $i]["name"]);
                $hedef_dosya = $hedef_klasor . $dosya_adi;
                
                if (move_uploaded_file($_FILES['resim_' . $i]["tmp_name"], $hedef_dosya)) {
                    // Eski resmi sil
                    $resim_index = $i - 1;
                    if (!empty($urun['resim_url_' . $resim_index])) {
                        @unlink($urun['resim_url_' . $resim_index]);
                    }
                    
                    // Yeni resim yolunu kaydet
                    $stmt = $pdo->prepare("UPDATE urunler SET resim_url_" . $resim_index . " = :resim_url WHERE id = :id");
                    $stmt->execute([
                        ':resim_url' => $hedef_dosya,
                        ':id' => $urun_id
                    ]);
                }
            }
        }
        
        $_SESSION['basari_mesaji'] = "Ürün başarıyla güncellendi.";
        header("Location: urun_detay.php?id=" . $urun_id);
        exit;
        
    } catch(PDOException $e) {
        $_SESSION['hata_mesaji'] = "Güncelleme hatası: " . $e->getMessage();
    }
}
?>

<div class="p-4">
    <!-- Geri Butonu -->
    <button 
        onclick="window.location.href='urun_detay.php?id=<?= $urun_id ?>'" 
        class="flex items-center mb-4 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
    >
        <i class="ri-arrow-left-line mr-2"></i> Geri
    </button>

    <?php if (isset($_SESSION['hata_mesaji'])): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
            <span class="block sm:inline"><?= $_SESSION['hata_mesaji'] ?></span>
        </div>
        <?php unset($_SESSION['hata_mesaji']); ?>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-2xl font-bold mb-6">Ürün Düzenle</h1>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Temel Bilgiler -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ürün Adı</label>
                    <input 
                        type="text" 
                        name="urun_adi" 
                        value="<?= htmlspecialchars($urun['urun_adi']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" 
                        required
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ürün Kodu</label>
                    <input 
                        type="text" 
                        name="urun_kodu" 
                        value="<?= htmlspecialchars($urun['urun_kodu']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Barkod</label>
                    <input 
                        type="text" 
                        name="barkod" 
                        value="<?= htmlspecialchars($urun['barkod']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Marka</label>
                    <select 
                        name="marka_id" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Marka Seçin</option>
                        <?php foreach($markalar as $marka): ?>
                            <option 
                                value="<?= $marka['id'] ?>" 
                                <?= $marka['id'] == $urun['marka_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($marka['marka_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Kategori</label>
                    <select 
                        name="kategori_id" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Kategori Seçin</option>
                        <?php foreach($kategoriler as $kategori): ?>
                            <option 
                                value="<?= $kategori['id'] ?>" 
                                <?= $kategori['id'] == $urun['kategori_id'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($kategori['kategori_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Fiyat ve Stok Bilgileri -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Birim Fiyat</label>
                    <input 
                        type="text" 
                        name="birim_fiyat" 
                        value="<?= number_format($urun['birim_fiyat'], 2, ',', '.') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" 
                        required
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">KDV Oranı (%)</label>
                    <input 
                        type="number" 
                        name="kdv_orani" 
                        value="<?= $urun['kdv_orani'] ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" 
                        required
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Stok Miktarı</label>
                    <input 
                        type="text" 
                        name="stok_miktari" 
                        value="<?= number_format($urun['stok_miktari'], 2, ',', '.') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20" 
                        required
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Minimum Stok</label>
                    <input 
                        type="text" 
                        name="minimum_stok" 
                        value="<?= number_format($urun['minimum_stok'], 2, ',', '.') ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Raf No</label>
                    <input 
                        type="text" 
                        name="raf_no" 
                        value="<?= htmlspecialchars($urun['raf_no']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Birim</label>
                    <select 
                        name="birim" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="Adet" <?= $urun['birim'] == 'Adet' ? 'selected' : '' ?>>Adet</option>
                        <option value="kg" <?= $urun['birim'] == 'kg' ? 'selected' : '' ?>>Kilogram (kg)</option>
                        <option value="gr" <?= $urun['birim'] == 'gr' ? 'selected' : '' ?>>Gram (gr)</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Ambalaj</label>
                    <input 
                        type="text" 
                        name="ambalaj" 
                        value="<?= htmlspecialchars($urun['ambalaj']) ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Koli Adeti</label>
                    <input 
                        type="number" 
                        name="koli_adeti" 
                        value="<?= $urun['koli_adeti'] ?>"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                </div>
            </div>

            <!-- Açıklama -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Açıklama</label>
                <textarea 
                    name="aciklama" 
                    rows="4" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20"
                ><?= htmlspecialchars($urun['aciklama']) ?></textarea>
            </div>

            <!-- Resim -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Ürün Resimleri</label>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4">
                    <?php for($i = 1; $i <= 10; $i++): ?>
                        <div class="relative">
                            <?php 
                            $resim_url = $i === 1 ? $urun['resim_url'] : $urun['resim_url_' . ($i-1)];
                            if (!empty($resim_url)): 
                            ?>
                                <div class="group relative">
                                    <img 
                                        src="<?= htmlspecialchars($resim_url) ?>" 
                                        alt="Ürün resmi <?= $i ?>" 
                                        class="w-full h-32 object-contain rounded-lg border"
                                    >
                                    <button 
                                        type="button"
                                        onclick="deleteImage(<?= $urun_id ?>, <?= $i === 1 ? 0 : $i-1 ?>)"
                                        class="absolute top-2 right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="border-2 border-gray-300 border-dashed rounded-lg p-4 hover:border-primary/50 transition-colors relative">
                                    <input 
                                        type="file" 
                                        name="resim_<?= $i ?>" 
                                        id="resim_<?= $i ?>" 
                                        class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                        accept="image/*"
                                        onchange="previewImage(this, <?= $i ?>)"
                                    >
                                    <div class="text-center" id="preview_container_<?= $i ?>">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <p class="mt-1 text-xs text-gray-500">Resim <?= $i ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                <p class="text-xs text-gray-500 text-center">PNG, JPG veya GIF (max. 2MB)</p>
            </div>

            <!-- Durum -->
            <div>
                <label class="flex items-center">
                    <input 
                        type="checkbox" 
                        name="durum" 
                        value="1" 
                        <?= $urun['aktif'] ? 'checked' : '' ?>
                        class="rounded text-primary focus:ring-primary"
                    >
                    <span class="ml-2 text-sm text-gray-600">Aktif</span>
                </label>
            </div>

            <!-- Butonlar -->
            <div class="flex justify-between items-center pt-6 border-t">
                <button 
                    type="submit"
                    class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-primary/90"
                >
                    Değişiklikleri Kaydet
                </button>
                
                <button 
                    type="button" 
                    onclick="urunSil(<?= $urun['id'] ?>)" 
                    class="px-6 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600"
                >
                    <i class="ri-delete-bin-line mr-2"></i>
                    Ürünü Sil
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewImage(input, index) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        const previewContainer = document.getElementById('preview_container_' + index);
        
        reader.onload = function(e) {
            previewContainer.innerHTML = `
                <img src="${e.target.result}" class="mx-auto h-32 w-32 object-contain rounded-lg border">
            `;
        }
        
        reader.readAsDataURL(input.files[0]);
    }
}

function deleteImage(urunId, resimIndex) {
    if (confirm('Bu resmi silmek istediğinizden emin misiniz?')) {
        fetch('ajax/delete_product_image.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                urun_id: urunId,
                resim_index: resimIndex
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Resim silinirken bir hata oluştu: ' + data.error);
            }
        })
        .catch(error => {
            alert('Bir hata oluştu: ' + error);
        });
    }
}

function urunSil(urunId) {
    Swal.fire({
        title: 'Emin misiniz?',
        text: "Bu ürünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Evet, sil!',
        cancelButtonText: 'İptal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('urun_sil.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + urunId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Silindi!',
                        text: 'Ürün başarıyla silindi.',
                        icon: 'success'
                    }).then(() => {
                        window.location.href = 'urunler.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Hata!',
                        text: data.error || 'Ürün silinirken bir hata oluştu.',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    title: 'Hata!',
                    text: 'Bir hata oluştu: ' + error,
                    icon: 'error'
                });
            });
        }
    });
}
</script>

<?php include 'includes/footer.php'; ?> 
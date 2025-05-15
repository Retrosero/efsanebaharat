<?php
// roller.php
require_once 'includes/db.php';
include 'includes/header.php';

// Rolleri çek
try {
    $stmt = $pdo->query("SELECT * FROM roller ORDER BY id");
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

// Rol izinlerini çek
$rol_izinleri = [];
try {
    $stmt = $pdo->query("SELECT * FROM rol_sayfa_izinleri");
    $izinler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($izinler as $izin) {
        $rol_izinleri[$izin['rol_id']][$izin['sayfa_id']] = $izin['izin'];
    }
} catch(Exception $e) {
    // Hata durumunda boş dizi kullan
}

// Rol silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $rol_id = intval($_GET['sil']);
    
    // Yönetici rolü silinemez
    if ($rol_id === 1) {
        $hata_mesaji = "Yönetici rolü silinemez.";
    } else {
        try {
            // Önce bu role sahip kullanıcı var mı kontrol et
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar WHERE rol_id = :rol_id");
            $stmt->execute([':rol_id' => $rol_id]);
            $kullanici_sayisi = $stmt->fetchColumn();
            
            if ($kullanici_sayisi > 0) {
                $hata_mesaji = "Bu role sahip kullanıcılar olduğu için silinemez.";
            } else {
                $pdo->beginTransaction();
                
                // Önce rol izinlerini sil
                $stmt = $pdo->prepare("DELETE FROM rol_sayfa_izinleri WHERE rol_id = :rol_id");
                $stmt->execute([':rol_id' => $rol_id]);
                
                // Sonra rolü sil
                $stmt = $pdo->prepare("DELETE FROM roller WHERE id = :id");
                $stmt->execute([':id' => $rol_id]);
                
                $pdo->commit();
                
                // Başarılı silme işlemi sonrası yönlendirme
                header("Location: roller.php?mesaj=silindi");
                exit;
            }
        } catch(Exception $e) {
            $pdo->rollBack();
            $hata_mesaji = "Rol silinemedi: " . $e->getMessage();
        }
    }
}

// Rol ekleme işlemi
if (isset($_POST['rol_ekle'])) {
    $rol_adi = trim($_POST['rol_adi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    
    if (empty($rol_adi)) {
        $hata_mesaji = "Rol adı gereklidir.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO roller (rol_adi, aciklama) VALUES (:rol_adi, :aciklama)");
            $stmt->execute([
                ':rol_adi' => $rol_adi,
                ':aciklama' => $aciklama
            ]);
            
            // Başarılı ekleme işlemi sonrası yönlendirme
            header("Location: roller.php?mesaj=eklendi");
            exit;
        } catch(Exception $e) {
            $hata_mesaji = "Rol eklenemedi: " . $e->getMessage();
        }
    }
}

// İzin güncelleme işlemi
if (isset($_POST['izin_guncelle'])) {
    $rol_id = intval($_POST['rol_id'] ?? 0);
    $sayfa_izinleri = $_POST['sayfa_izinleri'] ?? [];
    
    if ($rol_id <= 0) {
        $hata_mesaji = "Geçersiz rol ID.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Önce bu role ait tüm izinleri sil
            $stmt = $pdo->prepare("DELETE FROM rol_sayfa_izinleri WHERE rol_id = :rol_id");
            $stmt->execute([':rol_id' => $rol_id]);
            
            // Yeni izinleri ekle
            $stmt = $pdo->prepare("
                INSERT INTO rol_sayfa_izinleri (rol_id, sayfa_id, izin)
                VALUES (:rol_id, :sayfa_id, 1)
            ");
            
            foreach ($sayfa_izinleri as $sayfa_id) {
                $stmt->execute([
                    ':rol_id' => $rol_id,
                    ':sayfa_id' => $sayfa_id
                ]);
            }
            
            $pdo->commit();
            
            // Başarılı güncelleme sonrası yönlendirme
            header("Location: roller.php?mesaj=guncellendi");
            exit;
        } catch(Exception $e) {
            $pdo->rollBack();
            $hata_mesaji = "İzinler güncellenemedi: " . $e->getMessage();
        }
    }
}

// Başarı mesajları
$mesaj = '';
if (isset($_GET['mesaj'])) {
    switch ($_GET['mesaj']) {
        case 'eklendi':
            $mesaj = "Rol başarıyla eklendi.";
            break;
        case 'guncellendi':
            $mesaj = "Rol izinleri başarıyla güncellendi.";
            break;
        case 'silindi':
            $mesaj = "Rol başarıyla silindi.";
            break;
    }
}
?>

<div class="p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold">Rol Yönetimi</h1>
        <button 
            onclick="document.getElementById('rolEkleModal').classList.remove('hidden'); document.getElementById('rolEkleModal').classList.add('flex');"
            class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200 flex items-center gap-1"
        >
            <i class="ri-add-line"></i> Yeni Rol
        </button>
    </div>

    <?php if ($mesaj): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
            <p><?= $mesaj ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($hata_mesaji)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
            <p><?= $hata_mesaji ?></p>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol Adı</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($roller)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500">Henüz rol bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($roller as $rol): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $rol['id'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($rol['rol_adi']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($rol['aciklama'] ?? '') ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button 
                                        onclick="izinDuzenle(<?= $rol['id'] ?>, '<?= htmlspecialchars($rol['rol_adi']) ?>')"
                                        class="text-primary hover:text-primary-dark mr-3"
                                    >
                                        <i class="ri-lock-line"></i> İzinler
                                    </button>
                                    <?php if ($rol['id'] != 1): // Yönetici rolü silinemez ?>
                                        <a href="#" onclick="silmeOnayi(<?= $rol['id'] ?>, '<?= htmlspecialchars($rol['rol_adi']) ?>')" class="text-red-600 hover:text-red-900">
                                            <i class="ri-delete-bin-line"></i> Sil
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Rol Ekle Modal -->
<div id="rolEkleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md mx-4">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-medium">Yeni Rol Ekle</h3>
        </div>
        <form method="post" class="p-4">
            <div class="mb-4">
                <label for="rol_adi" class="block text-sm font-medium text-gray-700 mb-1">Rol Adı</label>
                <input 
                    type="text" 
                    id="rol_adi" 
                    name="rol_adi" 
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    required
                >
            </div>
            <div class="mb-4">
                <label for="aciklama" class="block text-sm font-medium text-gray-700 mb-1">Açıklama</label>
                <textarea 
                    id="aciklama" 
                    name="aciklama" 
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
                    rows="3"
                ></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button 
                    type="button" 
                    onclick="document.getElementById('rolEkleModal').classList.remove('flex'); document.getElementById('rolEkleModal').classList.add('hidden');"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200"
                >
                    İptal
                </button>
                <button 
                    type="submit" 
                    name="rol_ekle" 
                    class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200"
                >
                    Ekle
                </button>
            </div>
        </form>
    </div>
</div>

<!-- İzin Düzenle Modal -->
<div id="izinDuzenleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-2xl mx-4">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-medium">Rol İzinleri: <span id="izinRolAdi"></span></h3>
        </div>
        <form method="post" class="p-4">
            <input type="hidden" id="izinRolId" name="rol_id" value="">
            
            <div class="mb-4">
                <p class="text-sm text-gray-600 mb-2">Bu role sahip kullanıcıların erişebileceği sayfaları seçin:</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-4">
                    <?php foreach($sayfalar as $sayfa): ?>
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                id="sayfa_<?= $sayfa['id'] ?>" 
                                name="sayfa_izinleri[]" 
                                value="<?= $sayfa['id'] ?>" 
                                class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded"
                            >
                            <label for="sayfa_<?= $sayfa['id'] ?>" class="ml-2 block text-sm text-gray-900">
                                <?= htmlspecialchars($sayfa['sayfa_adi']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="flex justify-end gap-2">
                <button 
                    type="button" 
                    onclick="document.getElementById('izinDuzenleModal').classList.remove('flex'); document.getElementById('izinDuzenleModal').classList.add('hidden');"
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200"
                >
                    İptal
                </button>
                <button 
                    type="submit" 
                    name="izin_guncelle" 
                    class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200"
                >
                    Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div id="silmeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md mx-4">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-medium">Rol Silme Onayı</h3>
        </div>
        <div class="p-4">
            <p class="mb-4">
                <span id="silinecekRol"></span> rolünü silmek istediğinize emin misiniz?
            </p>
            <div class="flex justify-end gap-2">
                <button 
                    id="iptalBtn" 
                    class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200"
                >
                    İptal
                </button>
                <a 
                    id="silBtn" 
                    href="#" 
                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200"
                >
                    Sil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// İzin düzenleme modalını aç
function izinDuzenle(rolId, rolAdi) {
    document.getElementById('izinRolId').value = rolId;
    document.getElementById('izinRolAdi').textContent = rolAdi;
    
    // Tüm checkbox'ları sıfırla
    document.querySelectorAll('input[name="sayfa_izinleri[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Rol izinlerini yükle
    <?php if (!empty($rol_izinleri)): ?>
        const rolIzinleri = <?= json_encode($rol_izinleri) ?>;
        
        if (rolIzinleri[rolId]) {
            for (const sayfaId in rolIzinleri[rolId]) {
                if (rolIzinleri[rolId][sayfaId]) {
                    const checkbox = document.getElementById('sayfa_' + sayfaId);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                }
            }
        }
    <?php endif; ?>
    
    // Modalı göster
    document.getElementById('izinDuzenleModal').classList.remove('hidden');
    document.getElementById('izinDuzenleModal').classList.add('flex');
}

// Silme onay modalını aç
function silmeOnayi(id, rolAdi) {
    document.getElementById('silinecekRol').textContent = rolAdi;
    document.getElementById('silBtn').href = 'roller.php?sil=' + id;
    
    const modal = document.getElementById('silmeModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

// İptal butonuna tıklandığında
document.getElementById('iptalBtn').addEventListener('click', function() {
    const modal = document.getElementById('silmeModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
});
</script>

<?php include 'includes/footer.php'; ?> 
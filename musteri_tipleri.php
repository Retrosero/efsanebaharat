<?php
require_once 'includes/db.php';
include 'includes/header.php';

$successMessage = '';
$errorMessage = '';

// Müşteri tipi ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $tip_adi = trim($_POST['tip_adi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    
    if (empty($tip_adi)) {
        $errorMessage = 'Müşteri tipi adı boş olamaz.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO musteri_tipleri (tip_adi, aciklama, created_at)
                VALUES (:tip_adi, :aciklama, NOW())
            ");
            
            $stmt->execute([
                ':tip_adi' => $tip_adi,
                ':aciklama' => $aciklama
            ]);
            
            $successMessage = 'Müşteri tipi başarıyla eklendi.';
        } catch (PDOException $e) {
            $errorMessage = 'Hata: ' . $e->getMessage();
        }
    }
}

// Müşteri tipi silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $tip_id = isset($_POST['tip_id']) ? intval($_POST['tip_id']) : 0;
    
    if ($tip_id > 0) {
        try {
            // Önce bu tipe sahip müşterilerin tip_id'sini NULL yap
            $stmtUpdate = $pdo->prepare("
                UPDATE musteriler SET tip_id = NULL WHERE tip_id = :tip_id
            ");
            $stmtUpdate->execute([':tip_id' => $tip_id]);
            
            // Sonra tipi sil
            $stmtDelete = $pdo->prepare("
                DELETE FROM musteri_tipleri WHERE id = :tip_id
            ");
            $stmtDelete->execute([':tip_id' => $tip_id]);
            
            $successMessage = 'Müşteri tipi başarıyla silindi.';
        } catch (PDOException $e) {
            $errorMessage = 'Hata: ' . $e->getMessage();
        }
    }
}

// Müşteri tiplerini listele
$stmt = $pdo->query("SELECT * FROM musteri_tipleri ORDER BY tip_adi");
$musteri_tipleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Müşteri Tipleri</h1>
        <a href="ayarlar.php" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-button flex items-center">
            <i class="ri-arrow-left-line mr-2"></i> Ayarlara Dön
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
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Müşteri Tipi Ekleme Formu -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Yeni Müşteri Tipi Ekle</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="mb-4">
                    <label for="tip_adi" class="block text-sm font-medium text-gray-700 mb-2">Tip Adı <span class="text-red-600">*</span></label>
                    <input 
                        type="text" 
                        id="tip_adi" 
                        name="tip_adi" 
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                        required
                    >
                </div>
                
                <div class="mb-4">
                    <label for="aciklama" class="block text-sm font-medium text-gray-700 mb-2">Açıklama</label>
                    <textarea 
                        id="aciklama" 
                        name="aciklama" 
                        rows="3" 
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary"
                    ></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button 
                        type="submit" 
                        class="bg-primary hover:bg-blue-600 text-white px-4 py-2 rounded-button"
                    >
                        Ekle
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Müşteri Tipleri Listesi -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Mevcut Müşteri Tipleri</h2>
            
            <?php if (empty($musteri_tipleri)): ?>
            <p class="text-gray-500">Henüz müşteri tipi eklenmemiş.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tip Adı</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
                            <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($musteri_tipleri as $tip): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium"><?= htmlspecialchars($tip['tip_adi']) ?></td>
                            <td class="px-4 py-3 text-sm text-gray-500"><?= htmlspecialchars($tip['aciklama'] ?: '-') ?></td>
                            <td class="px-4 py-3 text-center">
                                <form method="POST" action="" onsubmit="return confirm('Bu müşteri tipini silmek istediğinizden emin misiniz?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="tip_id" value="<?= $tip['id'] ?>">
                                    <button 
                                        type="submit" 
                                        class="text-red-600 hover:text-red-800"
                                        title="Sil"
                                    >
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 
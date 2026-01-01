<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Tahsilat ID'sini al
$tahsilat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Tahsilat bilgilerini çek
$tahsilat = null;
try {
    $stmt = $pdo->prepare("
        SELECT t.*, m.ad as musteri_adi, m.soyad as musteri_soyad 
        FROM odeme_tahsilat t
        JOIN musteriler m ON t.musteri_id = m.id
        WHERE t.id = :id
    ");
    $stmt->execute([':id' => $tahsilat_id]);
    $tahsilat = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// Tahsilat bulunamadıysa
if (!$tahsilat) {
    echo "<div class='p-4 text-red-600'>Tahsilat kaydı bulunamadı.</div>";
    include 'includes/footer.php';
    exit;
}

// Form gönderildiğinde
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tutar = floatval($_POST['tutar'] ?? 0);
    $odeme_yontemi = $_POST['odeme_yontemi'] ?? '';
    $islem_tarihi = $_POST['islem_tarihi'] ?? date('Y-m-d');
    $aciklama = trim($_POST['aciklama'] ?? '');

    if ($tutar > 0) {
        try {
            // Eski tutarı al
            $stmtOld = $pdo->prepare("SELECT tutar FROM odeme_tahsilat WHERE id = :id");
            $stmtOld->execute([':id' => $tahsilat_id]);
            $oldTutar = $stmtOld->fetchColumn();

            // Tahsilatı güncelle
            $stmt = $pdo->prepare("
                UPDATE odeme_tahsilat 
                SET tutar = :tutar,
                    odeme_yontemi = :odeme_yontemi,
                    islem_tarihi = :islem_tarihi
                WHERE id = :id
            ");
            
            $stmt->execute([
                ':tutar' => $tutar,
                ':odeme_yontemi' => $odeme_yontemi,
                ':islem_tarihi' => $islem_tarihi,
                ':id' => $tahsilat_id
            ]);

            // Müşteri cari bakiyesini güncelle
            $fark = $oldTutar - $tutar; // Eski tutar - Yeni tutar
            if ($fark != 0) {
                $stmtCari = $pdo->prepare("
                    UPDATE musteriler 
                    SET cari_bakiye = cari_bakiye + :fark
                    WHERE id = :musteri_id
                ");
                $stmtCari->execute([
                    ':fark' => $fark,
                    ':musteri_id' => $tahsilat['musteri_id']
                ]);
            }

            $successMessage = 'Tahsilat başarıyla güncellendi.';
            
            // Güncel tahsilat bilgilerini yeniden çek
            $stmt = $pdo->prepare("
                SELECT t.*, m.ad as musteri_adi, m.soyad as musteri_soyad 
                FROM odeme_tahsilat t
                JOIN musteriler m ON t.musteri_id = m.id
                WHERE t.id = :id
            ");
            $stmt->execute([':id' => $tahsilat_id]);
            $tahsilat = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch(Exception $e) {
            $errorMessage = "Güncelleme hatası: " . $e->getMessage();
        }
    } else {
        $errorMessage = "Geçersiz tutar girişi.";
    }
}
?>

<div class="p-4">
    <!-- Geri Butonu -->
    <button 
        onclick="history.back()" 
        class="flex items-center mb-4 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
    >
        <i class="ri-arrow-left-line mr-2"></i> Geri
    </button>

    <?php if($successMessage): ?>
        <div class="bg-green-100 text-green-800 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if($errorMessage): ?>
        <div class="bg-red-100 text-red-800 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($errorMessage) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-sm p-6">
        <h1 class="text-2xl font-bold mb-6">Tahsilat Düzenle</h1>
        
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-700">
                Müşteri: <?= htmlspecialchars($tahsilat['musteri_adi'] . ' ' . $tahsilat['musteri_soyad']) ?>
            </h2>
            <p class="text-gray-600">Tahsilat No: #<?= $tahsilat_id ?></p>
        </div>

        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Tutar -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tutar
                    </label>
                    <div class="relative">
                        <input 
                            type="text" 
                            name="tutar"
                            value="<?= number_format($tahsilat['tutar'], 2, '.', '') ?>"
                            class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            required
                        >
                        <div class="absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                            TL
                        </div>
                    </div>
                </div>

                <!-- Ödeme Yöntemi -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Ödeme Yöntemi
                    </label>
                    <select 
                        name="odeme_yontemi"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                        <?php
                        $yontemler = ['nakit', 'kredi', 'havale', 'eft', 'cek', 'senet'];
                        foreach($yontemler as $y):
                            $selected = $y === $tahsilat['odeme_yontemi'] ? 'selected' : '';
                        ?>
                            <option value="<?= $y ?>" <?= $selected ?>><?= ucfirst($y) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- İşlem Tarihi -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        İşlem Tarihi
                    </label>
                    <input 
                        type="date"
                        name="islem_tarihi"
                        value="<?= date('Y-m-d', strtotime($tahsilat['islem_tarihi'])) ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                </div>

                <!-- Açıklama -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Açıklama
                    </label>
                    <textarea 
                        name="aciklama"
                        rows="3"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                    ><?= htmlspecialchars($tahsilat['aciklama'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex justify-end space-x-4 pt-6 border-t">
                <button 
                    type="button"
                    onclick="history.back()"
                    class="px-6 py-2 border rounded-button hover:bg-gray-50"
                >
                    İptal
                </button>
                <button 
                    type="submit"
                    class="px-6 py-2 bg-primary text-white rounded-button hover:bg-primary/90"
                >
                    Değişiklikleri Kaydet
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Tutar input formatı
document.querySelector('input[name="tutar"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d.]/g, '');
    let parts = value.split('.');
    if (parts.length > 1) {
        parts[1] = parts[1].slice(0, 2);
        value = parts.join('.');
    }
    e.target.value = value;
});
</script>

<?php include 'includes/footer.php'; ?> 
<?php
// kullanicilar.php
require_once 'includes/db.php';
include 'includes/header.php';

// Kullanıcıları veritabanından çek
try {
    $stmt = $pdo->query("
        SELECT k.*, r.rol_adi
        FROM kullanicilar k
        JOIN roller r ON k.rol_id = r.id
        ORDER BY k.id DESC
    ");
    $kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Hata: " . $e->getMessage();
    $kullanicilar = [];
}

// Rolleri çek
try {
    $stmt = $pdo->query("SELECT * FROM roller ORDER BY rol_adi");
    $roller = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Hata: " . $e->getMessage();
    $roller = [];
}

// Kullanıcı silme işlemi
if (isset($_GET['sil']) && is_numeric($_GET['sil'])) {
    $kullanici_id = intval($_GET['sil']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM kullanicilar WHERE id = :id");
        $stmt->execute([':id' => $kullanici_id]);
        
        // Başarılı silme işlemi sonrası yönlendirme
        header("Location: kullanicilar.php?mesaj=silindi");
        exit;
    } catch(Exception $e) {
        $hata_mesaji = "Kullanıcı silinemedi: " . $e->getMessage();
    }
}

// Başarı mesajları
$mesaj = '';
if (isset($_GET['mesaj'])) {
    switch ($_GET['mesaj']) {
        case 'eklendi':
            $mesaj = "Kullanıcı başarıyla eklendi.";
            break;
        case 'guncellendi':
            $mesaj = "Kullanıcı başarıyla güncellendi.";
            break;
        case 'silindi':
            $mesaj = "Kullanıcı başarıyla silindi.";
            break;
    }
}
?>

<div class="p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold">Kullanıcı Yönetimi</h1>
        <a href="kullanici_ekle.php" class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200 flex items-center gap-1">
            <i class="ri-add-line"></i> Yeni Kullanıcı
        </a>
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
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kullanıcı Adı</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">E-posta</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rol</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($kullanicilar)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">Henüz kullanıcı bulunmuyor.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($kullanicilar as $kullanici): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= $kullanici['id'] ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($kullanici['kullanici_adi']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($kullanici['eposta']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-500"><?= htmlspecialchars($kullanici['rol_adi']) ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($kullanici['aktif']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                            Pasif
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="kullanici_duzenle.php?id=<?= $kullanici['id'] ?>" class="text-primary hover:text-primary-dark mr-3">
                                        <i class="ri-edit-line"></i> Düzenle
                                    </a>
                                    <a href="#" onclick="silmeOnayi(<?= $kullanici['id'] ?>, '<?= htmlspecialchars($kullanici['kullanici_adi']) ?>')" class="text-red-600 hover:text-red-900">
                                        <i class="ri-delete-bin-line"></i> Sil
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div id="silmeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg w-full max-w-md mx-4">
        <div class="p-4 border-b border-gray-200">
            <h3 class="text-lg font-medium">Kullanıcı Silme Onayı</h3>
        </div>
        <div class="p-4">
            <p class="mb-4">
                <span id="silinecekKullanici"></span> kullanıcısını silmek istediğinize emin misiniz?
            </p>
            <div class="flex justify-end gap-2">
                <button id="iptalBtn" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition duration-200">
                    İptal
                </button>
                <a id="silBtn" href="#" class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition duration-200">
                    Sil
                </a>
            </div>
        </div>
    </div>
</div>

<script>
function silmeOnayi(id, kullaniciAdi) {
    document.getElementById('silinecekKullanici').textContent = kullaniciAdi;
    document.getElementById('silBtn').href = 'kullanicilar.php?sil=' + id;
    
    const modal = document.getElementById('silmeModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

document.getElementById('iptalBtn').addEventListener('click', function() {
    const modal = document.getElementById('silmeModal');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
});
</script>

<?php include 'includes/footer.php'; ?> 
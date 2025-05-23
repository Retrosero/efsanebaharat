<?php
// tediye_makbuzlari.php - Tediye makbuzlarını listelemek için
require_once 'includes/db.php';
include 'includes/header.php';

// Tarih filtresi
$baslangic_tarihi = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-01');
$bitis_tarihi = isset($_GET['bitis']) ? $_GET['bitis'] : date('Y-m-t');

// Müşteri filtresi
$musteri_id = isset($_GET['musteri_id']) ? intval($_GET['musteri_id']) : 0;

// Sayfalama için parametreler
$sayfa = isset($_GET['sayfa']) ? intval($_GET['sayfa']) : 1;
$limit = 20;
$offset = ($sayfa - 1) * $limit;

try {
    // Filtre koşulları
    $where_conditions = ["islem_turu = 'tediye'"];
    $params = [];

    // Tarih filtresi ekle
    if (!empty($baslangic_tarihi) && !empty($bitis_tarihi)) {
        $where_conditions[] = "islem_tarihi BETWEEN :baslangic AND :bitis";
        $params[':baslangic'] = $baslangic_tarihi;
        $params[':bitis'] = $bitis_tarihi;
    }

    // Müşteri filtresi ekle
    if ($musteri_id > 0) {
        $where_conditions[] = "musteri_id = :musteri_id";
        $params[':musteri_id'] = $musteri_id;
    }

    // WHERE koşulunu oluştur
    $where_clause = implode(' AND ', $where_conditions);

    // Toplam kayıt sayısını al
    $countSql = "SELECT COUNT(*) FROM odeme_tahsilat WHERE " . $where_clause;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);

    // Tediye makbuzlarını getir
    $sql = "
        SELECT ot.*, 
               CONCAT(m.ad, ' ', m.soyad) AS musteri_adi,
               u.adsoyad AS kullanici_adi
        FROM odeme_tahsilat ot
        LEFT JOIN musteriler m ON ot.musteri_id = m.id
        LEFT JOIN kullanicilar u ON ot.kullanici_id = u.id
        WHERE $where_clause
        ORDER BY ot.islem_tarihi DESC, ot.id DESC
        LIMIT :offset, :limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    
    // Diğer parametreleri ekle
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    $makbuzlar = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Müşteri listesini getir
    $musteriStmt = $pdo->query("SELECT id, ad, soyad FROM musteriler WHERE aktif = 1 ORDER BY ad, soyad");
    $musteriler = $musteriStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $makbuzlar = [];
    $musteriler = [];
    $total_pages = 0;
}
?>

<div class="p-4 sm:p-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Tediye Makbuzları</h1>
        <p class="text-gray-600">Müşterilere yapılan ödeme kayıtlarını görüntüleyin ve yönetin.</p>
    </div>

    <?php if (isset($error)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Filtreler -->
    <div class="bg-white p-4 rounded-lg shadow-sm mb-6">
        <form method="GET" action="" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Müşteri Filtresi -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Müşteri</label>
                    <select name="musteri_id" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                        <option value="0">Tüm Müşteriler</option>
                        <?php foreach ($musteriler as $musteri): ?>
                            <option value="<?= $musteri['id'] ?>" <?= $musteri_id == $musteri['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Tarih Aralığı -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Başlangıç Tarihi</label>
                    <input type="date" name="baslangic" value="<?= htmlspecialchars($baslangic_tarihi) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bitiş Tarihi</label>
                    <input type="date" name="bitis" value="<?= htmlspecialchars($bitis_tarihi) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-sm">
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                    <i class="ri-filter-line mr-1"></i> Filtrele
                </button>
            </div>
        </form>
    </div>

    <!-- Makbuz Listesi -->
    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Makbuz No</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Müşteri</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ödeme Türü</th>
                        <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Onay Durumu</th>
                        <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                    <?php if (empty($makbuzlar)): ?>
                        <tr>
                            <td colspan="7" class="py-4 px-4 text-center text-gray-500">Kayıt bulunamadı</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($makbuzlar as $makbuz): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-4 whitespace-nowrap"><?= htmlspecialchars($makbuz['evrak_no']) ?></td>
                                <td class="py-2 px-4 whitespace-nowrap"><?= date('d.m.Y', strtotime($makbuz['islem_tarihi'])) ?></td>
                                <td class="py-2 px-4"><?= htmlspecialchars($makbuz['musteri_adi']) ?></td>
                                <td class="py-2 px-4 whitespace-nowrap">
                                    <?php
                                        $odemeTurleri = [
                                            'nakit' => 'Nakit',
                                            'kredi' => 'Kredi Kartı',
                                            'havale' => 'Havale',
                                            'cek' => 'Çek',
                                            'senet' => 'Senet'
                                        ];
                                        echo htmlspecialchars($odemeTurleri[$makbuz['odeme_turu']] ?? $makbuz['odeme_turu']);
                                    ?>
                                </td>
                                <td class="py-2 px-4 text-right font-medium"><?= number_format($makbuz['tutar'], 2, ',', '.') ?> ₺</td>
                                <td class="py-2 px-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                        <?php
                                        switch ($makbuz['onay_durumu']) {
                                            case 'onaylandi':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'bekliyor':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'reddedildi':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?= ucfirst($makbuz['onay_durumu']) ?>
                                    </span>
                                </td>
                                <td class="py-2 px-4 whitespace-nowrap text-sm">
                                    <div class="flex items-center space-x-2">
                                        <a href="tahsilat_detay.php?id=<?= $makbuz['id'] ?>" class="text-primary hover:text-blue-600">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="tediye_makbuz.php?id=<?= $makbuz['id'] ?>" class="text-gray-600 hover:text-gray-900">
                                            <i class="ri-printer-line"></i>
                                        </a>
                                        <?php if ($makbuz['onay_durumu'] == 'bekliyor'): ?>
                                            <a href="tahsilat_duzenle.php?id=<?= $makbuz['id'] ?>" class="text-gray-600 hover:text-gray-900">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Sayfalama -->
        <?php if ($total_pages > 1): ?>
        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">
                    Toplam <span class="font-medium"><?= $total_records ?></span> kayıt
                </div>
                <div class="flex space-x-1">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?sayfa=<?= $i ?>&musteri_id=<?= $musteri_id ?>&baslangic=<?= $baslangic_tarihi ?>&bitis=<?= $bitis_tarihi ?>" 
                           class="px-3 py-1 <?= $i == $sayfa ? 'bg-primary text-white' : 'bg-white text-gray-700 hover:bg-gray-50' ?> rounded border text-sm">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Yeni Tediye Ekleme Butonu -->
    <div class="mt-6 flex justify-end">
        <a href="tediye.php" class="px-4 py-2 bg-primary text-white rounded-button hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
            <i class="ri-add-line mr-1"></i> Yeni Tediye Ekle
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?> 
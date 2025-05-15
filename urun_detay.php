<?php
// urun_detay.php
require_once 'includes/db.php';
include 'includes/header.php';

// Ürün ID'sini al
$urun_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Ürün bilgilerini çek
$urun = null;
try {
    $stmt = $pdo->prepare("
        SELECT u.*, 
               m.marka_adi,
               a.ambalaj_adi
        FROM urunler u
        LEFT JOIN markalar m ON u.marka_id = m.id
        LEFT JOIN ambalaj_tipleri a ON u.ambalaj_id = a.id
        WHERE u.id = :id
    ");
    $stmt->execute([':id' => $urun_id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}

// Ürün bulunamadıysa
if (!$urun) {
    echo "<div class='p-4 text-red-600'>Ürün bulunamadı.</div>";
    include 'includes/footer.php';
    exit;
}

// Ürün resimlerini bir diziye topla
$resimler = [];
for ($i = 1; $i <= 10; $i++) {
    $resim_key = "resim_url" . ($i > 1 ? "_$i" : "");
    if (!empty($urun[$resim_key])) {
        $resimler[] = $urun[$resim_key];
    }
}

// Ürün stok hareketlerini çek (son 10 hareket)
$stok_hareketleri = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            fd.miktar, 
            fd.birim_fiyat,
            f.id as fatura_id,
            f.fatura_turu,
            f.fatura_tarihi as tarih,
            CASE 
                WHEN f.fatura_turu = 'satis' THEN CONCAT(m.ad, ' ', m.soyad)
                WHEN f.fatura_turu = 'alis' THEN CONCAT(t.ad, ' ', t.soyad)
            END AS firma_adi
        FROM fatura_detaylari fd
        JOIN faturalar f ON fd.fatura_id = f.id
        LEFT JOIN musteriler m ON f.musteri_id = m.id AND f.fatura_turu = 'satis'
        LEFT JOIN musteriler t ON f.tedarikci_id = t.id AND f.fatura_turu = 'alis'
        WHERE fd.urun_id = :urun_id
        ORDER BY f.fatura_tarihi DESC
        LIMIT 10
    ");
    $stmt->execute([':urun_id' => $urun_id]);
    $stok_hareketleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // Hata durumunda boş dizi kullan
}
?>

<div class="p-4">
    <div class="flex justify-between items-center mb-4">
        <h1 class="text-xl font-semibold">Ürün Detayları</h1>
        <div class="flex gap-2">
            <a href="urunler.php" class="flex items-center gap-1 px-3 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                <i class="ri-arrow-left-line"></i> Ürünlere Dön
            </a>
            <a href="urun_duzenle.php?id=<?= $urun_id ?>" class="flex items-center gap-1 px-3 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors">
                <i class="ri-edit-line"></i> Düzenle
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Sol Kolon: Ürün Resimleri -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-4">
                    <h2 class="text-lg font-medium mb-4">Ürün Görselleri</h2>
                    
                    <?php if (empty($resimler)): ?>
                        <div class="flex items-center justify-center h-64 bg-gray-100 rounded-lg">
                            <p class="text-gray-500">Ürün görseli bulunmuyor</p>
                        </div>
                    <?php else: ?>
                        <!-- Ana Resim -->
                        <div class="mb-4">
                            <img id="mainImage" src="<?= htmlspecialchars($resimler[0]) ?>" alt="<?= htmlspecialchars($urun['urun_adi']) ?>" class="w-full h-64 object-contain rounded-lg border border-gray-200">
                        </div>
                        
                        <!-- Küçük Resimler -->
                        <?php if (count($resimler) > 1): ?>
                            <div class="grid grid-cols-5 gap-2">
                                <?php foreach($resimler as $index => $resim): ?>
                                    <div class="cursor-pointer border border-gray-200 rounded-lg overflow-hidden <?= $index === 0 ? 'ring-2 ring-primary' : '' ?>" onclick="changeMainImage('<?= htmlspecialchars($resim) ?>', this)">
                                        <img src="<?= htmlspecialchars($resim) ?>" alt="Küçük resim <?= $index + 1 ?>" class="w-full h-16 object-contain">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sağ Kolon: Ürün Bilgileri -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-4">
                <div class="p-4">
                    <h2 class="text-lg font-medium mb-4">Ürün Bilgileri</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Ürün Adı</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['urun_adi']) ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Ürün Kodu</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['urun_kodu'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Barkod</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['barkod'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Marka</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['marka_adi'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Kategori</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['kategori'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Ambalaj Tipi</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['ambalaj_adi'] ?? $urun['ambalaj'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Koli Adeti</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['koli_adeti'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Raf No</h3>
                            <p class="text-gray-900"><?= htmlspecialchars($urun['raf_no'] ?? 'Belirtilmemiş') ?></p>
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <div class="text-gray-600">Stok Miktarı</div>
                            <div class="font-medium">
                                <?php 
                                $stok_miktari = $urun['stok_miktari'];
                                $olcum_birimi = $urun['olcum_birimi'];
                                
                                if ($olcum_birimi === 'kg') {
                                    // kg'yi gr'a çevir
                                    echo number_format($stok_miktari * 1000, 0, ',', '') . ' gr';
                                } else if ($olcum_birimi === 'gr') {
                                    echo number_format($stok_miktari, 0, ',', '') . ' gr';
                                } else {
                                    echo number_format($stok_miktari, 0, ',', '') . ' ' . $olcum_birimi;
                                }
                                ?>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Birim Fiyat</h3>
                            <p class="text-gray-900 font-semibold"><?= number_format($urun['birim_fiyat'], 2, ',', '.') ?> TL</p>
                        </div>
                    </div>
                    
                    <?php if (!empty($urun['aciklama'])): ?>
                        <div class="mt-4">
                            <h3 class="text-sm font-medium text-gray-500 mb-1">Açıklama</h3>
                            <p class="text-gray-900"><?= nl2br(htmlspecialchars($urun['aciklama'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stok Hareketleri -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-4">
                    <h2 class="text-lg font-medium mb-4">Stok Hareketleri</h2>
                    
                    <?php if (empty($stok_hareketleri)): ?>
                        <p class="text-gray-500">Henüz stok hareketi bulunmuyor.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Firma</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach($stok_hareketleri as $hareket): ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location.href='fatura_detay.php?id=<?= $hareket['fatura_id'] ?>'">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= date('d.m.Y', strtotime($hareket['tarih'])) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($hareket['fatura_turu'] == 'satis'): ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        Satış
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Alış
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= htmlspecialchars($hareket['firma_adi']) ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= number_format($hareket['miktar'], 0, ',', '.') ?> adet
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= number_format($hareket['birim_fiyat'], 2, ',', '.') ?> TL
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?= number_format($hareket['miktar'] * $hareket['birim_fiyat'], 2, ',', '.') ?> TL
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
    </div>
</div>

<script>
function changeMainImage(src, element) {
    // Ana resmi değiştir
    document.getElementById('mainImage').src = src;
    
    // Tüm küçük resimlerin seçimini kaldır
    document.querySelectorAll('.cursor-pointer').forEach(el => {
        el.classList.remove('ring-2', 'ring-primary');
    });
    
    // Seçilen resmi vurgula
    element.classList.add('ring-2', 'ring-primary');
}
</script>

<?php include 'includes/footer.php'; ?> 
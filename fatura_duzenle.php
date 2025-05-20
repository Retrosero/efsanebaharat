<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Fatura ID'sini al
$fatura_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fatura ve detaylarını çek
$fatura = null;
$fatura_detaylari = [];
try {
    // Ana fatura bilgileri
    $stmt = $pdo->prepare("
        SELECT f.*, 
               CASE 
                 WHEN f.fatura_turu = 'satis' THEN m.ad 
                 WHEN f.fatura_turu = 'alis' THEN t.ad 
               END AS musteri_adi,
               CASE 
                 WHEN f.fatura_turu = 'satis' THEN m.soyad 
                 WHEN f.fatura_turu = 'alis' THEN t.soyad 
               END AS musteri_soyad
        FROM faturalar f
        LEFT JOIN musteriler m ON f.musteri_id = m.id AND f.fatura_turu = 'satis'
        LEFT JOIN musteriler t ON f.tedarikci_id = t.id AND f.fatura_turu = 'alis'
        WHERE f.id = :id
    ");
    $stmt->execute([':id' => $fatura_id]);
    $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fatura detayları
    if ($fatura) {
        $stmtD = $pdo->prepare("
            SELECT fd.*, u.urun_adi
            FROM fatura_detaylari fd
            JOIN urunler u ON fd.urun_id = u.id
            WHERE fd.fatura_id = :fid
        ");
        $stmtD->execute([':fid' => $fatura_id]);
        $fatura_detaylari = $stmtD->fetchAll(PDO::FETCH_ASSOC);
    }
} catch(Exception $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// Fatura bulunamadıysa
if (!$fatura) {
    echo "<div class='p-4 text-red-600'>Fatura bulunamadı.</div>";
    include 'includes/footer.php';
    exit;
}

// Form gönderildiğinde
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tarih = $_POST['tarih'] ?? date('Y-m-d');
    
    // Ürün detayları
    $urun_idler = $_POST['urun_id'] ?? [];
    $miktarlar = $_POST['miktar'] ?? [];
    $birim_fiyatlar = $_POST['birim_fiyat'] ?? [];

    try {
        $pdo->beginTransaction();

        // Eski detayları al (stok iadesi için)
        $stmtEski = $pdo->prepare("
            SELECT urun_id, miktar 
            FROM fatura_detaylari 
            WHERE fatura_id = :fid
        ");
        $stmtEski->execute([':fid' => $fatura_id]);
        $eskiDetaylar = $stmtEski->fetchAll(PDO::FETCH_ASSOC);

        // Eski stokları iade et
        foreach($eskiDetaylar as $eski) {
            $stmtStokIade = $pdo->prepare("
                UPDATE urunler 
                SET stok_miktari = stok_miktari + :qty
                WHERE id = :uid
            ");
            $stmtStokIade->execute([
                ':qty' => $eski['miktar'],
                ':uid' => $eski['urun_id']
            ]);
        }

        // Yeni stok kontrolü
        foreach($urun_idler as $key => $urun_id) {
            $miktar = floatval($miktarlar[$key]);
            
            // Stok kontrolü
            $stmtStok = $pdo->prepare("SELECT stok_miktari FROM urunler WHERE id = ?");
            $stmtStok->execute([$urun_id]);
            $stok = $stmtStok->fetchColumn();
            
            if ($stok < $miktar) {
                throw new Exception('Yetersiz stok! Ürün ID: '.$urun_id);
            }
        }

        // Eski toplam tutarı al
        $stmtOld = $pdo->prepare("SELECT toplam_tutar FROM faturalar WHERE id = :id");
        $stmtOld->execute([':id' => $fatura_id]);
        $oldToplam = $stmtOld->fetchColumn();

        // Yeni toplam tutarı hesapla
        $yeni_toplam = 0;
        foreach($miktarlar as $key => $miktar) {
            $yeni_toplam += $miktar * $birim_fiyatlar[$key];
        }

        // Faturayı güncelle
        $stmt = $pdo->prepare("
            UPDATE faturalar 
            SET fatura_tarihi = :tarih,
                toplam_tutar = :toplam_tutar,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':tarih' => $tarih,
            ':toplam_tutar' => $yeni_toplam,
            ':id' => $fatura_id
        ]);

        // Eski detayları sil
        $stmtDel = $pdo->prepare("DELETE FROM fatura_detaylari WHERE fatura_id = :fid");
        $stmtDel->execute([':fid' => $fatura_id]);

        // Yeni detayları ekle ve stokları güncelle
        $stmtIns = $pdo->prepare("
            INSERT INTO fatura_detaylari (
                fatura_id, urun_id, miktar, birim_fiyat
            ) VALUES (
                :fatura_id, :urun_id, :miktar, :birim_fiyat
            )
        ");

        foreach($urun_idler as $key => $urun_id) {
            $miktar = floatval($miktarlar[$key]);
            $birim_fiyat = floatval($birim_fiyatlar[$key]);
            
            // Fatura detayı ekle
            $stmtIns->execute([
                ':fatura_id' => $fatura_id,
                ':urun_id' => $urun_id,
                ':miktar' => $miktar,
                ':birim_fiyat' => $birim_fiyat
            ]);

            // Stok güncelle
            $stmtStokGuncelle = $pdo->prepare("
                UPDATE urunler 
                SET stok_miktari = stok_miktari - :qty,
                    updated_at = NOW()
                WHERE id = :uid
            ");
            $stmtStokGuncelle->execute([
                ':qty' => $miktar,
                ':uid' => $urun_id
            ]);
        }

        // Müşteri cari bakiyesini güncelle
        $fark = $yeni_toplam - $oldToplam;
        if ($fark != 0) {
            $stmtCari = $pdo->prepare("
                UPDATE musteriler 
                SET cari_bakiye = cari_bakiye + :fark
                WHERE id = :musteri_id
            ");
            $stmtCari->execute([
                ':fark' => $fark,
                ':musteri_id' => $fatura['musteri_id']
            ]);
        }

        $pdo->commit();
        $successMessage = 'Fatura başarıyla güncellendi.';

        // Güncel fatura bilgilerini yeniden çek
        $stmt = $pdo->prepare("
            SELECT f.*, 
                   CASE 
                     WHEN f.fatura_turu = 'satis' THEN m.ad 
                     WHEN f.fatura_turu = 'alis' THEN t.ad 
                   END AS musteri_adi,
                   CASE 
                     WHEN f.fatura_turu = 'satis' THEN m.soyad 
                     WHEN f.fatura_turu = 'alis' THEN t.soyad 
                   END AS musteri_soyad
            FROM faturalar f
            LEFT JOIN musteriler m ON f.musteri_id = m.id AND f.fatura_turu = 'satis'
            LEFT JOIN musteriler t ON f.tedarikci_id = t.id AND f.fatura_turu = 'alis'
            WHERE f.id = :id
        ");
        $stmt->execute([':id' => $fatura_id]);
        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        // Güncel fatura detaylarını yeniden çek
        $stmtD = $pdo->prepare("
            SELECT fd.*, u.urun_adi
            FROM fatura_detaylari fd
            JOIN urunler u ON fd.urun_id = u.id
            WHERE fd.fatura_id = :fid
        ");
        $stmtD->execute([':fid' => $fatura_id]);
        $fatura_detaylari = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    } catch(Exception $e) {
        $pdo->rollBack();
        $errorMessage = 'Güncelleme hatası: ' . $e->getMessage();
    }
}

// Ürün listesini çek
$urunler = [];
try {
    $stmt = $pdo->query("SELECT id, urun_adi, birim_fiyat, stok_miktari FROM urunler ORDER BY urun_adi");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    // ignore
}

// Ürün fiyatlarını JSON formatına çevir
$urunFiyatlari = [];
$urunlerJson = [];
foreach ($urunler as $urun) {
    $urunFiyatlari[$urun['id']] = $urun['birim_fiyat'];
    $urunlerJson[] = [
        'id' => $urun['id'],
        'urun_adi' => $urun['urun_adi'],
        'birim_fiyat' => $urun['birim_fiyat'],
        'stok_miktari' => $urun['stok_miktari']
    ];
}
$urunFiyatlariJson = json_encode($urunFiyatlari);
$urunlerListesiJson = json_encode($urunlerJson);
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
        <h1 class="text-2xl font-bold mb-6">Fatura Düzenle</h1>
        
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-700">
                Müşteri: <?= htmlspecialchars($fatura['musteri_adi'] . ' ' . $fatura['musteri_soyad']) ?>
            </h2>
            <p class="text-gray-600">Fatura No: <?= htmlspecialchars($fatura['fatura_no']) ?></p>
        </div>

        <form method="POST" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Fatura No -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Fatura No
                    </label>
                    <input 
                        type="text"
                        name="fatura_no"
                        value="<?= htmlspecialchars($fatura['fatura_no']) ?>"
                        class="w-full px-4 py-2 bg-gray-50 border rounded-lg"
                        readonly
                    >
                </div>

                <!-- Tarih -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Tarih
                    </label>
                    <input 
                        type="date"
                        name="tarih"
                        value="<?= date('Y-m-d', strtotime($fatura['fatura_tarihi'])) ?>"
                        class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                        required
                    >
                </div>
            </div>

            <!-- Ürünler -->
            <div class="mt-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-700">Ürünler</h3>
                    
                    <!-- Yeni Ürün Ekle Butonu -->
                    <button 
                        type="button" 
                        onclick="yeniUrunSatiriEkle()"
                        class="px-4 py-2 bg-primary text-white rounded-button text-sm flex items-center hover:bg-blue-600 transition-colors"
                    >
                        <i class="ri-add-line mr-1"></i> Yeni Ürün Ekle
                    </button>
                </div>
                
                <div id="urunler-container">
                    <?php foreach($fatura_detaylari as $index => $detay): ?>
                    <div class="urun-satir grid grid-cols-1 md:grid-cols-5 gap-4 mb-4">
                        <!-- Ürün Seçimi -->
                        <div class="md:col-span-2 relative">
                            <div class="urun-search-container">
                                <input 
                                    type="text" 
                                    class="urun-search-input w-full px-4 py-2 border rounded-lg" 
                                    placeholder="Ürün ara..."
                                    data-index="<?= $index ?>"
                                >
                                <div class="urun-search-results hidden absolute z-10 w-full bg-white border rounded-lg mt-1 max-h-60 overflow-y-auto shadow-lg"></div>
                            </div>
                            <input type="hidden" name="urun_id[]" value="<?= $detay['urun_id'] ?>" class="urun-id-input">
                            <div class="selected-urun mt-1 text-sm text-gray-600">
                                Seçili: <?= htmlspecialchars($detay['urun_adi']) ?>
                            </div>
                        </div>
                        
                        <!-- Miktar -->
                        <div>
                            <input 
                                type="number"
                                name="miktar[]"
                                value="<?= $detay['miktar'] ?>"
                                class="w-full px-4 py-2 border rounded-lg"
                                min="1"
                                step="1"
                                required
                                onchange="toplamHesapla(this)"
                                onkeyup="toplamHesapla(this)"
                            >
                        </div>
                        
                        <!-- Birim Fiyat -->
                        <div>
                            <input 
                                type="number"
                                name="birim_fiyat[]"
                                value="<?= $detay['birim_fiyat'] ?>"
                                class="w-full px-4 py-2 border rounded-lg"
                                min="0"
                                step="0.01"
                                required
                                onchange="toplamHesapla(this)"
                                onkeyup="toplamHesapla(this)"
                            >
                        </div>
                        
                        <!-- Toplam + Sil Butonu -->
                        <div class="relative">
                            <input 
                                type="text"
                                value="₺<?= number_format($detay['miktar'] * $detay['birim_fiyat'], 2, ',', '.') ?>"
                                class="w-full px-4 py-2 bg-gray-50 border rounded-lg"
                                readonly
                            >
                            <button 
                                type="button"
                                class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800"
                                onclick="this.closest('.urun-satir').remove(); genelToplamHesapla()"
                            >
                                <i class="ri-delete-bin-line"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Ürün Yok Mesajı -->
                <div id="urunYokMesaji" class="<?= count($fatura_detaylari) > 0 ? 'hidden' : '' ?> p-4 text-center text-gray-500 border border-dashed rounded-lg">
                    Henüz ürün eklenmemiş. Yukarıdaki "Yeni Ürün Ekle" butonunu kullanarak ürün ekleyebilirsiniz.
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
// Ürün fiyatları ve listesi
const urunFiyatlari = <?= $urunFiyatlariJson ?>;
const urunlerListesi = <?= $urunlerListesiJson ?>;

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
    // Mevcut ürün satırları için arama özelliğini aktifleştir
    document.querySelectorAll('.urun-search-input').forEach(input => {
        setupUrunSearch(input);
    });
    
    // Genel toplamı hesapla
    genelToplamHesapla();
    
    // Mevcut satırların sil butonlarını güncelle
    document.querySelectorAll('.urun-satir button[onclick*="this.closest"]').forEach(button => {
        button.setAttribute('onclick', 'silUrunSatiri(this)');
    });
});

// Ürün arama ve seçme fonksiyonu
function setupUrunSearch(input) {
    const container = input.closest('.urun-search-container');
    const resultsDiv = container.querySelector('.urun-search-results');
    const urunIdInput = input.closest('.urun-satir').querySelector('.urun-id-input');
    const selectedUrunDiv = input.closest('.urun-satir').querySelector('.selected-urun');
    const birimFiyatInput = input.closest('.urun-satir').querySelector('input[name="birim_fiyat[]"]');
    
    // Arama yapıldığında
    input.addEventListener('input', function() {
        const searchText = this.value.toLowerCase();
        
        if (searchText.length < 2) {
            resultsDiv.classList.add('hidden');
            return;
        }
        
        // Arama sonuçlarını göster
        const filteredUrunler = urunlerListesi.filter(urun => 
            urun.urun_adi.toLowerCase().includes(searchText)
        );
        
        if (filteredUrunler.length === 0) {
            resultsDiv.innerHTML = '<div class="p-3 text-gray-500">Sonuç bulunamadı</div>';
        } else {
            resultsDiv.innerHTML = filteredUrunler.map(urun => `
                <div class="urun-result p-3 hover:bg-gray-50 cursor-pointer border-b" 
                     data-id="${urun.id}" 
                     data-name="${urun.urun_adi}" 
                     data-price="${urun.birim_fiyat}">
                    <div class="font-medium">${urun.urun_adi}</div>
                    <div class="text-sm text-gray-500">
                        Fiyat: ₺${parseFloat(urun.birim_fiyat).toLocaleString('tr-TR', {minimumFractionDigits: 2})} | 
                        Stok: ${urun.stok_miktari}
                    </div>
                </div>
            `).join('');
            
            // Ürün seçme olayını ekle
            resultsDiv.querySelectorAll('.urun-result').forEach(item => {
                item.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const price = this.dataset.price;
                    
                    // Seçilen ürünü göster
                    selectedUrunDiv.textContent = `Seçili: ${name}`;
                    
                    // ID'yi gizli alana ekle
                    urunIdInput.value = id;
                    
                    // Fiyatı doldur
                    birimFiyatInput.value = price;
                    
                    // Arama kutusunu temizle ve sonuçları gizle
                    input.value = '';
                    resultsDiv.classList.add('hidden');
                    
                    // Toplam hesapla
                    toplamHesapla(birimFiyatInput);
                });
            });
        }
        
        resultsDiv.classList.remove('hidden');
    });
    
    // Dışarı tıklandığında sonuçları gizle
    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            resultsDiv.classList.add('hidden');
        }
    });
}

// Yeni ürün satırı ekleme
function yeniUrunSatiriEkle() {
    const container = document.getElementById('urunler-container');
    
    // Ürün yok mesajını gizle
    document.getElementById('urunYokMesaji').classList.add('hidden');
    
    const yeniSatir = document.createElement('div');
    yeniSatir.className = 'urun-satir grid grid-cols-1 md:grid-cols-5 gap-4 mb-4';
    
    yeniSatir.innerHTML = `
        <!-- Ürün Seçimi -->
        <div class="md:col-span-2 relative">
            <div class="urun-search-container">
                <input 
                    type="text" 
                    class="urun-search-input w-full px-4 py-2 border rounded-lg" 
                    placeholder="Ürün ara..."
                >
                <div class="urun-search-results hidden absolute z-10 w-full bg-white border rounded-lg mt-1 max-h-60 overflow-y-auto shadow-lg"></div>
            </div>
            <input type="hidden" name="urun_id[]" value="" class="urun-id-input" required>
            <div class="selected-urun mt-1 text-sm text-gray-600">
                Henüz ürün seçilmedi
            </div>
        </div>
        
        <!-- Miktar -->
        <div>
            <input 
                type="number"
                name="miktar[]"
                value="1"
                class="w-full px-4 py-2 border rounded-lg"
                min="1"
                step="1"
                required
                onchange="toplamHesapla(this)"
                onkeyup="toplamHesapla(this)"
            >
        </div>
        
        <!-- Birim Fiyat -->
        <div>
            <input 
                type="number"
                name="birim_fiyat[]"
                value="0"
                class="w-full px-4 py-2 border rounded-lg"
                min="0"
                step="0.01"
                required
                onchange="toplamHesapla(this)"
                onkeyup="toplamHesapla(this)"
            >
        </div>
        
        <!-- Toplam + Sil Butonu -->
        <div class="relative">
            <input 
                type="text"
                value="₺0,00"
                class="w-full px-4 py-2 bg-gray-50 border rounded-lg"
                readonly
            >
            <button 
                type="button"
                class="absolute right-2 top-1/2 -translate-y-1/2 text-red-600 hover:text-red-800"
                onclick="silUrunSatiri(this)"
            >
                <i class="ri-delete-bin-line"></i>
            </button>
        </div>
    `;
    
    container.appendChild(yeniSatir);
    
    // Yeni satır için arama özelliğini aktifleştir
    setupUrunSearch(yeniSatir.querySelector('.urun-search-input'));
    
    // Arama kutusuna otomatik odaklan
    yeniSatir.querySelector('.urun-search-input').focus();
}

// Ürün satırını silme
function silUrunSatiri(button) {
    const satirDiv = button.closest('.urun-satir');
    satirDiv.remove();
    genelToplamHesapla();
    
    // Eğer hiç ürün kalmadıysa mesajı göster
    const container = document.getElementById('urunler-container');
    if (container.children.length === 0) {
        document.getElementById('urunYokMesaji').classList.remove('hidden');
    }
}

// Toplam hesaplama
function toplamHesapla(input) {
    const satir = input.closest('.urun-satir');
    const miktar = parseFloat(satir.querySelector('input[name="miktar[]"]').value) || 0;
    const birimFiyat = parseFloat(satir.querySelector('input[name="birim_fiyat[]"]').value) || 0;
    const toplam = miktar * birimFiyat;
    
    satir.querySelector('input[readonly]').value = 
        '₺' + toplam.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    
    genelToplamHesapla();
}

// Genel toplam hesaplama
function genelToplamHesapla() {
    const satirlar = document.querySelectorAll('.urun-satir');
    let genelToplam = 0;
    
    satirlar.forEach(satir => {
        const miktar = parseFloat(satir.querySelector('input[name="miktar[]"]').value) || 0;
        const birimFiyat = parseFloat(satir.querySelector('input[name="birim_fiyat[]"]').value) || 0;
        genelToplam += miktar * birimFiyat;
    });
    
    document.getElementById('genelToplam').textContent = 
        '₺' + genelToplam.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}
</script>

<?php include 'includes/footer.php'; ?> 
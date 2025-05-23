<?php
require_once 'includes/db.php';
include 'includes/header.php';

// Varsayılan değerler ve filtreleme kontrolü
$baslangic_tarihi = isset($_GET['baslangic']) ? $_GET['baslangic'] : date('Y-m-d', strtotime('-7 days'));
$bitis_tarihi = isset($_GET['bitis']) ? $_GET['bitis'] : date('Y-m-d');
$rapor_tipi = isset($_GET['rapor_tipi']) ? $_GET['rapor_tipi'] : 'gunluk';
$kategori_id = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;

// Rapor tipi için zaman dilimi formatlarını ayarla
$zaman_formati = [
    'gunluk' => ['format' => 'Y-m-d', 'label' => 'd.m.Y', 'group_by' => 'DATE(f.fatura_tarihi)'],
    'haftalik' => ['format' => 'Y-W', 'label' => '\H\a\f\t\a W, Y', 'group_by' => 'YEARWEEK(f.fatura_tarihi)'],
    'aylik' => ['format' => 'Y-m', 'label' => 'F Y', 'group_by' => 'DATE_FORMAT(f.fatura_tarihi, "%Y-%m")']
];

try {
    // Kategorileri getir
    $stmt_kategoriler = $pdo->query("SELECT id, kategori_adi FROM kategoriler ORDER BY kategori_adi");
    $kategoriler = $stmt_kategoriler->fetchAll(PDO::FETCH_ASSOC);
    
    // SQL sorgusu için kategori filtresi
    $kategori_filtresi = '';
    $kategori_params = [];
    
    if ($kategori_id > 0) {
        $kategori_filtresi = "AND u.kategori_id = :kategori_id";
        $kategori_params[':kategori_id'] = $kategori_id;
    }
    
    // Karlılık verilerini getir (alış fiyatlarını ürün alış faturalarından hesapla)
    $sql = "
        SELECT
            {$zaman_formati[$rapor_tipi]['group_by']} AS zaman_dilimi,
            SUM(fd.net_tutar) AS toplam_satis,
            SUM(
                fd.miktar * COALESCE(
                    (
                        SELECT AVG(fd_alis.birim_fiyat)
                        FROM fatura_detaylari fd_alis
                        JOIN faturalar f_alis ON fd_alis.fatura_id = f_alis.id
                        WHERE fd_alis.urun_id = fd.urun_id
                        AND f_alis.fatura_turu = 'alis'
                        AND f_alis.iptal = 0
                    ), 
                    u.alis_fiyati
                )
            ) AS toplam_maliyet,
            SUM(
                fd.net_tutar - fd.miktar * COALESCE(
                    (
                        SELECT AVG(fd_alis.birim_fiyat)
                        FROM fatura_detaylari fd_alis
                        JOIN faturalar f_alis ON fd_alis.fatura_id = f_alis.id
                        WHERE fd_alis.urun_id = fd.urun_id
                        AND f_alis.fatura_turu = 'alis'
                        AND f_alis.iptal = 0
                    ), 
                    u.alis_fiyati
                )
            ) AS toplam_kar,
            (
                SUM(
                    fd.net_tutar - fd.miktar * COALESCE(
                        (
                            SELECT AVG(fd_alis.birim_fiyat)
                            FROM fatura_detaylari fd_alis
                            JOIN faturalar f_alis ON fd_alis.fatura_id = f_alis.id
                            WHERE fd_alis.urun_id = fd.urun_id
                            AND f_alis.fatura_turu = 'alis'
                            AND f_alis.iptal = 0
                        ), 
                        u.alis_fiyati
                    )
                ) / SUM(fd.net_tutar)
            ) * 100 AS kar_orani
        FROM
            fatura_detaylari fd
        JOIN 
            faturalar f ON fd.fatura_id = f.id
        JOIN 
            urunler u ON fd.urun_id = u.id
        WHERE
            f.fatura_turu = 'satis'
            AND f.iptal = 0
            AND f.fatura_tarihi BETWEEN :baslangic AND :bitis
            $kategori_filtresi
        GROUP BY
            zaman_dilimi
        ORDER BY
            zaman_dilimi
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':baslangic', $baslangic_tarihi);
    $stmt->bindParam(':bitis', $bitis_tarihi);
    
    // Kategori filtresi varsa parametreleri ekle
    if ($kategori_id > 0) {
        $stmt->bindParam(':kategori_id', $kategori_id);
    }
    
    $stmt->execute();
    $karlilik_verileri = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ürün bazlı karlılık raporu (alış fiyatlarını ürün alış faturalarından hesapla)
    $sql_urun = "
        SELECT
            u.id,
            u.urun_adi,
            SUM(fd.miktar) AS toplam_miktar,
            u.birim AS birim,
            SUM(fd.net_tutar) AS toplam_satis,
            SUM(
                fd.miktar * COALESCE(
                    (
                        SELECT AVG(fd_alis.birim_fiyat)
                        FROM fatura_detaylari fd_alis
                        JOIN faturalar f_alis ON fd_alis.fatura_id = f_alis.id
                        WHERE fd_alis.urun_id = fd.urun_id
                        AND f_alis.fatura_turu = 'alis'
                        AND f_alis.iptal = 0
                    ), 
                    u.alis_fiyati
                )
            ) AS toplam_maliyet,
            SUM(
                fd.net_tutar - fd.miktar * COALESCE(
                    (
                        SELECT AVG(fd_alis.birim_fiyat)
                        FROM fatura_detaylari fd_alis
                        JOIN faturalar f_alis ON fd_alis.fatura_id = f_alis.id
                        WHERE fd_alis.urun_id = fd.urun_id
                        AND f_alis.fatura_turu = 'alis'
                        AND f_alis.iptal = 0
                    ), 
                    u.alis_fiyati
                )
            ) AS toplam_kar,
            (
                SUM(
                    fd.net_tutar - fd.miktar * COALESCE(
                        (
                            SELECT AVG(fd_alis.birim_fiyat)
                            FROM fatura_detaylari fd_alis
                            JOIN faturalar f_alis ON fd_alis.fatura_id = f_alis.id
                            WHERE fd_alis.urun_id = fd.urun_id
                            AND f_alis.fatura_turu = 'alis'
                            AND f_alis.iptal = 0
                        ), 
                        u.alis_fiyati
                    )
                ) / SUM(fd.net_tutar)
            ) * 100 AS kar_orani
        FROM
            fatura_detaylari fd
        JOIN 
            faturalar f ON fd.fatura_id = f.id
        JOIN 
            urunler u ON fd.urun_id = u.id
        WHERE
            f.fatura_turu = 'satis'
            AND f.iptal = 0
            AND f.fatura_tarihi BETWEEN :baslangic AND :bitis
            $kategori_filtresi
        GROUP BY
            u.id
        ORDER BY
            toplam_kar DESC
        LIMIT 10
    ";
    
    $stmt_urun = $pdo->prepare($sql_urun);
    $stmt_urun->bindParam(':baslangic', $baslangic_tarihi);
    $stmt_urun->bindParam(':bitis', $bitis_tarihi);
    
    // Kategori filtresi varsa parametreleri ekle
    if ($kategori_id > 0) {
        $stmt_urun->bindParam(':kategori_id', $kategori_id);
    }
    
    $stmt_urun->execute();
    $urun_bazli_karlilik = $stmt_urun->fetchAll(PDO::FETCH_ASSOC);
    
    // Toplam değerleri hesapla
    $toplam_satis = 0;
    $toplam_maliyet = 0;
    $toplam_kar = 0;
    
    foreach ($karlilik_verileri as $veri) {
        $toplam_satis += $veri['toplam_satis'];
        $toplam_maliyet += $veri['toplam_maliyet'];
        $toplam_kar += $veri['toplam_kar'];
    }
    
    $toplam_kar_orani = ($toplam_satis > 0) ? (($toplam_kar / $toplam_satis) * 100) : 0;
    
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
    exit;
}
?>

<div class="p-4 sm:p-6">
    <!-- Başlık ve Filtreler -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-4">Karlılık Raporu</h1>
        
        <form method="GET" class="bg-white p-4 rounded-lg shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Başlangıç Tarihi</label>
                    <input 
                        type="date" 
                        name="baslangic" 
                        value="<?= htmlspecialchars($baslangic_tarihi) ?>"
                        class="w-full border border-gray-300 rounded px-3 py-2"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bitiş Tarihi</label>
                    <input 
                        type="date" 
                        name="bitis" 
                        value="<?= htmlspecialchars($bitis_tarihi) ?>"
                        class="w-full border border-gray-300 rounded px-3 py-2"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rapor Tipi</label>
                    <select name="rapor_tipi" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="gunluk" <?= $rapor_tipi == 'gunluk' ? 'selected' : '' ?>>Günlük</option>
                        <option value="haftalik" <?= $rapor_tipi == 'haftalik' ? 'selected' : '' ?>>Haftalık</option>
                        <option value="aylik" <?= $rapor_tipi == 'aylik' ? 'selected' : '' ?>>Aylık</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                    <select name="kategori_id" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="0">Tüm Kategoriler</option>
                        <?php foreach ($kategoriler as $kategori): ?>
                            <option value="<?= $kategori['id'] ?>" <?= $kategori_id == $kategori['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($kategori['kategori_adi']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary/90">
                    <i class="ri-filter-line mr-1"></i> Filtrele
                </button>
            </div>
        </form>
    </div>
    
    <!-- Özet Kartları -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <!-- Toplam Satış -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Satış</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($toplam_satis, 2, ',', '.') ?> ₺</p>
                </div>
                <div class="text-blue-500">
                    <i class="ri-shopping-cart-line text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Toplam Maliyet -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-orange-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Maliyet</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($toplam_maliyet, 2, ',', '.') ?> ₺</p>
                </div>
                <div class="text-orange-500">
                    <i class="ri-price-tag-3-line text-2xl"></i>
                </div>
            </div>
        </div>
        
        <!-- Toplam Kar -->
        <div class="bg-white rounded-lg shadow-sm p-4 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Toplam Kar</p>
                    <p class="text-xl font-bold text-gray-800"><?= number_format($toplam_kar, 2, ',', '.') ?> ₺</p>
                    <p class="text-sm text-gray-600">Kar Oranı: %<?= number_format($toplam_kar_orani, 2, ',', '.') ?></p>
                </div>
                <div class="text-green-500">
                    <i class="ri-line-chart-line text-2xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Zaman Dilimine Göre Karlılık Grafiği -->
    <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold text-gray-800">
                <?php
                $rapor_basliklari = [
                    'gunluk' => 'Günlük Karlılık Raporu',
                    'haftalik' => 'Haftalık Karlılık Raporu',
                    'aylik' => 'Aylık Karlılık Raporu'
                ];
                echo $rapor_basliklari[$rapor_tipi];
                ?>
            </h2>
        </div>
        
        <!-- Grafik -->
        <div style="height: 400px; position: relative;">
            <canvas id="karlilikGrafigi" class="p-4"></canvas>
        </div>
    </div>
    
    <!-- Detaylı Karlılık Tablosu -->
    <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold text-gray-800">Detaylı Karlılık Verileri</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Tarih
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Satış Tutarı
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Maliyet
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Kar
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Kar Oranı
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($karlilik_verileri)): ?>
                        <tr>
                            <td colspan="5" class="px-4 py-4 text-center text-gray-500">
                                Seçilen tarih aralığında veri bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($karlilik_verileri as $veri): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?php 
                                    if ($rapor_tipi == 'gunluk') {
                                        echo date('d.m.Y', strtotime($veri['zaman_dilimi']));
                                    } elseif ($rapor_tipi == 'haftalik') {
                                        list($yil, $hafta) = explode('-', date('Y-W', strtotime($veri['zaman_dilimi'])));
                                        echo "Hafta $hafta, $yil";
                                    } elseif ($rapor_tipi == 'aylik') {
                                        echo date('F Y', strtotime($veri['zaman_dilimi'] . '-01'));
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($veri['toplam_satis'], 2, ',', '.') ?> ₺
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($veri['toplam_maliyet'], 2, ',', '.') ?> ₺
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($veri['toplam_kar'], 2, ',', '.') ?> ₺
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    %<?= number_format($veri['kar_orani'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- En Karlı Ürünler -->
    <div class="bg-white rounded-lg shadow-sm mb-6 overflow-hidden">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold text-gray-800">En Karlı 10 Ürün</h2>
        </div>
        
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Ürün Adı
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Satış Miktarı
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Satış Tutarı
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Maliyet
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Kar
                        </th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Kar Oranı
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($urun_bazli_karlilik)): ?>
                        <tr>
                            <td colspan="6" class="px-4 py-4 text-center text-gray-500">
                                Seçilen tarih aralığında veri bulunamadı.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($urun_bazli_karlilik as $urun): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <?= htmlspecialchars($urun['urun_adi']) ?>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($urun['toplam_miktar'], 2, ',', '.') ?> <?= htmlspecialchars($urun['birim']) ?>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($urun['toplam_satis'], 2, ',', '.') ?> ₺
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($urun['toplam_maliyet'], 2, ',', '.') ?> ₺
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <?= number_format($urun['toplam_kar'], 2, ',', '.') ?> ₺
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    %<?= number_format($urun['kar_orani'], 2, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ChartJS Kütüphanesi -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('karlilikGrafigi').getContext('2d');
    
    // PHP'den gelen verileri JSON formatına dönüştürme
    const karlilikVerileri = <?= json_encode($karlilik_verileri) ?>;
    const raporTipi = '<?= $rapor_tipi ?>';
    
    // Grafik verileri
    const labels = [];
    const satisVerileri = [];
    const maliyetVerileri = [];
    const karVerileri = [];
    
    // Verileri formatlama
    karlilikVerileri.forEach(veri => {
        let formattedDate;
        if (raporTipi === 'gunluk') {
            // Güne göre format: DD.MM.YYYY
            const date = new Date(veri.zaman_dilimi);
            formattedDate = date.toLocaleDateString('tr-TR');
        } else if (raporTipi === 'haftalik') {
            // Haftaya göre format: "Hafta W, YYYY"
            const parts = veri.zaman_dilimi.toString().match(/(\d{4})(\d{2})/);
            if (parts) {
                formattedDate = `Hafta ${parseInt(parts[2])}, ${parts[1]}`;
            } else {
                formattedDate = veri.zaman_dilimi;
            }
        } else if (raporTipi === 'aylik') {
            // Aya göre format: "MMMM YYYY"
            const date = new Date(veri.zaman_dilimi + '-01');
            formattedDate = date.toLocaleDateString('tr-TR', { month: 'long', year: 'numeric' });
        }
        
        labels.push(formattedDate);
        satisVerileri.push(parseFloat(veri.toplam_satis));
        maliyetVerileri.push(parseFloat(veri.toplam_maliyet));
        karVerileri.push(parseFloat(veri.toplam_kar));
    });
    
    // Grafik oluşturma
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Satış',
                    data: satisVerileri,
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgb(59, 130, 246)',
                    borderWidth: 1
                },
                {
                    label: 'Maliyet',
                    data: maliyetVerileri,
                    backgroundColor: 'rgba(249, 115, 22, 0.5)',
                    borderColor: 'rgb(249, 115, 22)',
                    borderWidth: 1
                },
                {
                    label: 'Kar',
                    data: karVerileri,
                    backgroundColor: 'rgba(16, 185, 129, 0.5)',
                    borderColor: 'rgb(16, 185, 129)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('tr-TR') + ' ₺';
                        }
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += parseFloat(context.raw).toLocaleString('tr-TR') + ' ₺';
                            return label;
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?> 
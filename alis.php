<?php
// alis.php
require_once 'includes/db.php';
include 'includes/header.php'; // Soldaki menü + top bar layout

// Tedarikçileri çek (musteriler tablosundan)
$musteriRows = [];
try {
  // Müşterileri alfabetik sıraya göre getir (ad ve soyad'a göre)
  $stmtT = $pdo->query("SELECT id, ad, soyad, cari_bakiye FROM musteriler ORDER BY ad ASC, soyad ASC");
  $musteriRows = $stmtT->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
  // ignore
}

// Ürünleri veritabanından çek
$urunler = [];
try {
    $stmt = $pdo->query("
        SELECT 
            id,
            urun_adi,
            urun_kodu,
            barkod,
            birim_fiyat,
            stok_miktari,
            resim_url
        FROM urunler 
        ORDER BY urun_adi
    ");
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}

// Ürünleri JSON formatına çevir
$urunlerJson = json_encode($urunler, JSON_NUMERIC_CHECK);

// Form gönderildiğinde
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $musteri_id = $_POST['musteri_id'] ?? 0;
    $tarih = $_POST['tarih'] ?? date('Y-m-d');
    $iskonto_oran = isset($_POST['iskonto_oran']) ? floatval($_POST['iskonto_oran']) : 0;
    $iskonto_tutar = isset($_POST['iskonto_tutar']) ? floatval($_POST['iskonto_tutar']) : 0;
    
    // Ürün detayları
    $urun_idler = $_POST['urun_id'] ?? [];
    $miktarlar = $_POST['miktar'] ?? [];
    $birim_fiyatlar = $_POST['birim_fiyat'] ?? [];
    
    // Toplam tutarı hesapla
    $ara_toplam = 0;
    foreach($urun_idler as $key => $urun_id) {
        if (empty($urun_id)) continue;
        $miktar = floatval($miktarlar[$key]);
        $birim_fiyat = floatval($birim_fiyatlar[$key]);
        $ara_toplam += $miktar * $birim_fiyat;
    }
    
    // İskonto hesapla
    $iskonto_tutari = 0;
    if ($iskonto_oran > 0) {
        $iskonto_tutari = $ara_toplam * ($iskonto_oran / 100);
    } else {
        $iskonto_tutari = $iskonto_tutar;
    }
    
    // Toplam tutarı hesapla (iskonto düşülmüş)
    $toplam_tutar = $ara_toplam - $iskonto_tutari;
    
    try {
        $pdo->beginTransaction();
        
        // Fatura oluştur
        $stmtF = $pdo->prepare("
            INSERT INTO faturalar (
                fatura_turu, musteri_id, toplam_tutar, 
                odeme_durumu, fatura_tarihi, kullanici_id,
                indirim_tutari, genel_toplam, kalan_tutar
            ) VALUES (
                'alis', :musteri_id, :toplam_tutar, 
                'odenmedi', :tarih, :kullanici_id,
                :indirim_tutari, :genel_toplam, :kalan_tutar
            )
        ");
        
        $stmtF->execute([
            ':musteri_id' => $musteri_id,
            ':toplam_tutar' => $ara_toplam, // Ara toplam (indirim öncesi)
            ':tarih' => $tarih,
            ':kullanici_id' => 1, // Varsayılan kullanıcı ID
            ':indirim_tutari' => $iskonto_tutari,
            ':genel_toplam' => $toplam_tutar, // İndirim sonrası toplam
            ':kalan_tutar' => $toplam_tutar // Başlangıçta kalan tutar = genel toplam
        ]);
        
        $fatura_id = $pdo->lastInsertId();
        
        // Fatura detaylarını ekle
        $stmtD = $pdo->prepare("
            INSERT INTO fatura_detaylari (
                fatura_id, urun_id, urun_adi, miktar, birim_fiyat,
                toplam_fiyat, kdv_orani, kdv_tutari, 
                indirim_orani, indirim_tutari, net_tutar
            ) VALUES (
                :fatura_id, :urun_id, :urun_adi, :miktar, :birim_fiyat,
                :toplam_fiyat, :kdv_orani, :kdv_tutari,
                :indirim_orani, :indirim_tutari, :net_tutar
            )
        ");
        
        // Ürün bilgilerini çekmek için sorgu
        $stmtUrun = $pdo->prepare("SELECT urun_adi, kdv_orani FROM urunler WHERE id = :urun_id");
        
        // Stok güncelleme sorgusu
        $stmtStok = $pdo->prepare("
            UPDATE urunler 
            SET stok_miktari = stok_miktari + :miktar 
            WHERE id = :urun_id
        ");
        
        foreach($urun_idler as $key => $urun_id) {
            if (empty($urun_id)) continue;
            
            $miktar = floatval($miktarlar[$key]);
            $birim_fiyat = floatval($birim_fiyatlar[$key]);
            $toplam_fiyat = $miktar * $birim_fiyat;
            
            // Ürün bilgilerini çek
            $stmtUrun->execute([':urun_id' => $urun_id]);
            $urun = $stmtUrun->fetch(PDO::FETCH_ASSOC);
            $urun_adi = $urun['urun_adi'] ?? "Ürün #$urun_id";
            $kdv_orani = $urun['kdv_orani'] ?? 18.00;
            
            // KDV tutarını hesapla
            $kdv_tutari = $toplam_fiyat * ($kdv_orani / 100);
            
            // İndirim oranı ve tutarı (şimdilik 0)
            $indirim_orani = 0;
            $indirim_tutari = 0;
            
            // Net tutar
            $net_tutar = $toplam_fiyat + $kdv_tutari - $indirim_tutari;
            
            // Fatura detayını ekle
            $stmtD->execute([
                ':fatura_id' => $fatura_id,
                ':urun_id' => $urun_id,
                ':urun_adi' => $urun_adi,
                ':miktar' => $miktar,
                ':birim_fiyat' => $birim_fiyat,
                ':toplam_fiyat' => $toplam_fiyat,
                ':kdv_orani' => $kdv_orani,
                ':kdv_tutari' => $kdv_tutari,
                ':indirim_orani' => $indirim_orani,
                ':indirim_tutari' => $indirim_tutari,
                ':net_tutar' => $net_tutar
            ]);
            
            // Stok miktarını güncelle (alış olduğu için stok artırılıyor)
            $stmtStok->execute([
                ':miktar' => $miktar,
                ':urun_id' => $urun_id
            ]);
        }
        
        // Müşteri bakiyesini güncelle (alış faturası oluşturuldu)
        $stmtMusteriGuncelle = $pdo->prepare("
            UPDATE musteriler 
            SET cari_bakiye = cari_bakiye - :toplam_tutar 
            WHERE id = :musteri_id
        ");
        $stmtMusteriGuncelle->execute([
            ':toplam_tutar' => $toplam_tutar,
            ':musteri_id' => $musteri_id
        ]);
        
        // İşlem başarılı olduğunda log tutma
        error_log("Alış faturası eklendi: ID=$fatura_id, Tutar=$toplam_tutar, Müşteri ID=$musteri_id");
        
        $pdo->commit();
        $successMessage = "Alış faturası başarıyla kaydedildi.";
        
        // Başarılı kayıttan sonra formu temizle
        header("Location: fatura_detay.php?id=" . $fatura_id);
        exit;
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $errorMessage = "Hata: " . $e->getMessage();
    }
}
?>

<div class="p-4">
  <div class="flex justify-between items-center mb-4">
    <h1 class="text-2xl font-semibold text-gray-800">Müşteriden Alış Faturası</h1>
    <a href="alis_faturalari.php" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg flex items-center">
      <i class="ri-file-list-line mr-2"></i> Alış Faturaları
    </a>
  </div>
  
  <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-4">
    <div class="flex">
      <div class="flex-shrink-0">
        <i class="ri-information-line text-blue-500"></i>
      </div>
      <div class="ml-3">
        <p class="text-sm text-blue-700">
          Bu sayfada müşteriden alış faturası oluşturabilirsiniz. Müşteri seçtikten sonra ürünleri ekleyip faturayı kaydedebilirsiniz.
        </p>
      </div>
    </div>
  </div>

  <?php if ($successMessage): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
      <p><?= $successMessage ?></p>
    </div>
  <?php endif; ?>

  <?php if ($errorMessage): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
      <p><?= $errorMessage ?></p>
    </div>
  <?php endif; ?>

  <form method="post" id="alisFaturaForm" class="bg-white rounded-lg shadow-sm p-4">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
      <!-- Müşteri Seçimi -->
      <div>
        <label for="musteri_search" class="block text-sm font-medium text-gray-700 mb-1">Müşteri Ara</label>
        <div class="relative">
          <input 
            type="text" 
            id="musteri_search" 
            placeholder="Müşteri adı yazın..." 
            class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
          >
          <div id="musteri_results" class="absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
        </div>
        <select 
          id="musteri_id" 
          name="musteri_id" 
          class="hidden"
          required
        >
          <option value="">Müşteri Seçin</option>
          <?php foreach($musteriRows as $musteri): ?>
            <option value="<?= $musteri['id'] ?>" data-ad="<?= htmlspecialchars($musteri['ad']) ?>" data-soyad="<?= htmlspecialchars($musteri['soyad']) ?>">
              <?= htmlspecialchars($musteri['ad'] . ' ' . $musteri['soyad']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <div id="selected_musteri" class="mt-2 text-sm font-medium text-primary hidden"></div>
      </div>

      <!-- Fatura Tarihi -->
      <div>
        <label for="tarih" class="block text-sm font-medium text-gray-700 mb-1">Fatura Tarihi</label>
        <input 
          type="date" 
          id="tarih" 
          name="tarih" 
          value="<?= date('Y-m-d') ?>" 
          class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
          required
        >
      </div>
    </div>

    <!-- Ürün Tablosu -->
    <div class="mb-4">
      <h2 class="text-lg font-medium mb-2">Ürün Detayları</h2>
      <div class="overflow-x-auto">
        <table class="min-w-full bg-white border border-gray-200" id="urunTablosu">
          <thead>
            <tr class="bg-gray-50">
              <th class="py-2 px-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ürün</th>
              <th class="py-2 px-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Miktar(1Kg için 1000 gir)</th>
              <th class="py-2 px-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birim Fiyat</th>
              <th class="py-2 px-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Toplam</th>
              <th class="py-2 px-3 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem</th>
            </tr>
          </thead>
          <tbody id="urunSatirlar">
            <tr class="urun-satir">
              <td class="py-2 px-3 border-b">
                <div class="relative">
                  <input 
                    type="text" 
                    class="urun-search w-full rounded border border-gray-300 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary" 
                    placeholder="Ürün ara..."
                  >
                  <div class="urun-results absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto"></div>
                  <select name="urun_id[]" class="urun-select hidden" required>
                    <option value="">Ürün Seçin</option>
                    <?php foreach($urunler as $urun): ?>
                      <option value="<?= $urun['id'] ?>" data-fiyat="<?= $urun['birim_fiyat'] ?>" data-ad="<?= htmlspecialchars($urun['urun_adi']) ?>" data-kod="<?= htmlspecialchars($urun['urun_kodu']) ?>">
                        <?= htmlspecialchars($urun['urun_adi']) ?> (<?= $urun['urun_kodu'] ?>)
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <div class="selected-urun mt-1 text-xs font-medium text-primary hidden"></div>
                </div>
              </td>
              <td class="py-2 px-3 border-b">
                <input type="number" name="miktar[]" class="miktar-input w-full rounded border border-gray-300 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary" min="0.01" step="0.01" value="1" required>
              </td>
              <td class="py-2 px-3 border-b">
                <input type="number" name="birim_fiyat[]" class="fiyat-input w-full rounded border border-gray-300 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary" min="0.01" step="0.01" value="0.00" required>
              </td>
              <td class="py-2 px-3 border-b">
                <span class="satir-toplam">0.00</span> TL
              </td>
              <td class="py-2 px-3 border-b">
                <button type="button" class="sil-btn text-red-500 hover:text-red-700">
                  <i class="ri-delete-bin-line"></i>
                </button>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5" class="py-2 px-3 border-b">
                <button type="button" id="yeniSatirEkle" class="flex items-center text-primary hover:text-primary-dark">
                  <i class="ri-add-circle-line mr-1"></i> Yeni Ürün Ekle
                </button>
              </td>
            </tr>
            <tr>
              <td colspan="3" class="py-2 px-3 text-right font-medium">Ara Toplam:</td>
              <td class="py-2 px-3 font-bold">
                <span id="araToplam">0.00</span> TL
              </td>
              <td></td>
            </tr>
            <tr>
              <td colspan="3" class="py-2 px-3 text-right font-medium">
                <div class="flex items-center justify-end gap-2">
                  <span>İskonto:</span>
                  <div class="relative w-24">
                    <input 
                      type="number" 
                      id="iskonto_oran" 
                      name="iskonto_oran" 
                      class="w-full rounded border border-gray-300 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary" 
                      min="0" 
                      max="100" 
                      step="0.01" 
                      value="0"
                    >
                    <span class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">%</span>
                  </div>
                  <span>veya</span>
                  <div class="relative w-24">
                    <input 
                      type="number" 
                      id="iskonto_tutar" 
                      name="iskonto_tutar" 
                      class="w-full rounded border border-gray-300 px-2 py-1 focus:outline-none focus:ring-1 focus:ring-primary" 
                      min="0" 
                      step="0.01" 
                      value="0"
                    >
                    <span class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500">TL</span>
                  </div>
                </div>
              </td>
              <td class="py-2 px-3 font-bold text-red-500">
                -<span id="iskontoTutar">0.00</span> TL
              </td>
              <td></td>
            </tr>
            <tr>
              <td colspan="3" class="py-2 px-3 text-right font-medium">Genel Toplam:</td>
              <td class="py-2 px-3 font-bold">
                <span id="genelToplam">0.00</span> TL
              </td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Kaydet Butonu -->
    <div class="flex justify-end">
      <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-medium py-2 px-4 rounded-lg transition duration-200">
        <i class="ri-save-line mr-1"></i> Faturayı Kaydet
      </button>
    </div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Türkçe karakter dönüştürme fonksiyonu
  function turkishToLower(text) {
    const letters = {
      'İ': 'i', 'I': 'ı', 'Ş': 'ş', 'Ğ': 'ğ', 'Ü': 'ü', 'Ö': 'ö', 'Ç': 'ç',
      'i': 'i', 'ı': 'ı', 'ş': 'ş', 'ğ': 'ğ', 'ü': 'ü', 'ö': 'ö', 'ç': 'ç'
    };
    return text.replace(/[İIŞĞÜÖÇiışğüöç]/g, letter => letters[letter] || letter).toLowerCase();
  }

  function normalizeString(text) {
    const normalized = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    return normalized.replace(/[İIŞĞÜÖÇiışğüöç]/g, char => {
      return {
        'İ': 'i', 'I': 'i', 'Ş': 's', 'Ğ': 'g', 'Ü': 'u', 'Ö': 'o', 'Ç': 'c',
        'i': 'i', 'ı': 'i', 'ş': 's', 'ğ': 'g', 'ü': 'u', 'ö': 'o', 'ç': 'c'
      }[char] || char;
    }).toLowerCase();
  }

  // Ürün verilerini JavaScript'e aktar
  const urunler = <?= $urunlerJson ?>;
  
  // Müşteri arama
  const musteriSearch = document.getElementById('musteri_search');
  const musteriResults = document.getElementById('musteri_results');
  const musteriSelect = document.getElementById('musteri_id');
  const selectedMusteri = document.getElementById('selected_musteri');
  
  musteriSearch.addEventListener('input', function() {
    const searchTerm = this.value.trim();
    
    if (searchTerm.length < 2) {
      musteriResults.classList.add('hidden');
      return;
    }
    
    // Müşterileri filtrele
    const options = Array.from(musteriSelect.options).slice(1); // İlk option'ı (placeholder) atla
    const filteredOptions = options.filter(option => {
      const ad = turkishToLower(option.getAttribute('data-ad'));
      const soyad = turkishToLower(option.getAttribute('data-soyad'));
      const searchTermLower = turkishToLower(searchTerm);
      const normalizedSearchTerm = normalizeString(searchTerm);

      return ad.includes(searchTermLower) || 
             soyad.includes(searchTermLower) || 
             `${ad} ${soyad}`.includes(searchTermLower) ||
             normalizeString(ad).includes(normalizedSearchTerm) ||
             normalizeString(soyad).includes(normalizedSearchTerm) ||
             normalizeString(`${ad} ${soyad}`).includes(normalizedSearchTerm);
    });
    
    // Sonuçları göster
    musteriResults.innerHTML = '';
    
    if (filteredOptions.length === 0) {
      const noResult = document.createElement('div');
      noResult.className = 'p-2 text-sm text-gray-500';
      noResult.textContent = 'Sonuç bulunamadı';
      musteriResults.appendChild(noResult);
    } else {
      filteredOptions.forEach(option => {
        const resultItem = document.createElement('div');
        resultItem.className = 'p-2 text-sm hover:bg-gray-100 cursor-pointer';
        resultItem.textContent = option.textContent;
        resultItem.dataset.id = option.value;
        resultItem.dataset.ad = option.getAttribute('data-ad');
        resultItem.dataset.soyad = option.getAttribute('data-soyad');
        
        resultItem.addEventListener('click', function() {
          musteriSelect.value = this.dataset.id;
          musteriSearch.value = `${this.dataset.ad} ${this.dataset.soyad}`;
          selectedMusteri.textContent = `Seçilen Müşteri: ${this.dataset.ad} ${this.dataset.soyad}`;
          selectedMusteri.classList.remove('hidden');
          musteriResults.classList.add('hidden');
        });
        
        musteriResults.appendChild(resultItem);
      });
    }
    
    musteriResults.classList.remove('hidden');
  });
  
  // Müşteri arama kutusundan çıkıldığında
  document.addEventListener('click', function(e) {
    if (!musteriSearch.contains(e.target) && !musteriResults.contains(e.target)) {
      musteriResults.classList.add('hidden');
    }
  });
  
  // Yeni satır ekleme
  document.getElementById('yeniSatirEkle').addEventListener('click', function() {
    const satirTemplate = document.querySelector('.urun-satir').cloneNode(true);
    
    // Yeni satırın değerlerini sıfırla
    satirTemplate.querySelector('.urun-select').value = '';
    satirTemplate.querySelector('.urun-search').value = '';
    satirTemplate.querySelector('.selected-urun').textContent = '';
    satirTemplate.querySelector('.selected-urun').classList.add('hidden');
    satirTemplate.querySelector('.miktar-input').value = '1';
    satirTemplate.querySelector('.fiyat-input').value = '0.00';
    satirTemplate.querySelector('.satir-toplam').textContent = '0.00';
    
    // Silme butonuna event listener ekle
    satirTemplate.querySelector('.sil-btn').addEventListener('click', function() {
      if (document.querySelectorAll('.urun-satir').length > 1) {
        this.closest('tr').remove();
        hesaplaGenelToplam();
      } else {
        alert('En az bir ürün satırı olmalıdır.');
      }
    });
    
    // Yeni satıra event listener'ları ekle
    satirTemplate.querySelector('.urun-search').addEventListener('input', urunAra);
    satirTemplate.querySelector('.miktar-input').addEventListener('input', hesaplaSatirToplam);
    satirTemplate.querySelector('.fiyat-input').addEventListener('input', hesaplaSatirToplam);
    
    // Yeni satırı tabloya ekle
    document.getElementById('urunSatirlar').appendChild(satirTemplate);
  });
  
  // İlk satır için event listener'ları ekle
  document.querySelectorAll('.urun-satir').forEach(satir => {
    satir.querySelector('.urun-search').addEventListener('input', urunAra);
    satir.querySelector('.miktar-input').addEventListener('input', hesaplaSatirToplam);
    satir.querySelector('.fiyat-input').addEventListener('input', hesaplaSatirToplam);
    satir.querySelector('.sil-btn').addEventListener('click', function() {
      if (document.querySelectorAll('.urun-satir').length > 1) {
        this.closest('tr').remove();
        hesaplaGenelToplam();
      } else {
        alert('En az bir ürün satırı olmalıdır.');
      }
    });
  });
  
  // Ürün arama fonksiyonu
  function urunAra() {
    const searchTerm = this.value.trim();
    const satir = this.closest('tr');
    const urunResults = satir.querySelector('.urun-results');
    const urunSelect = satir.querySelector('.urun-select');
    
    if (searchTerm.length < 2) {
      urunResults.classList.add('hidden');
      return;
    }
    
    // Ürünleri filtrele
    const options = Array.from(urunSelect.options).slice(1); // İlk option'ı (placeholder) atla
    const filteredOptions = options.filter(option => {
      const urunAdi = turkishToLower(option.getAttribute('data-ad'));
      const urunKodu = turkishToLower(option.getAttribute('data-kod'));
      const searchTermLower = turkishToLower(searchTerm);
      const normalizedSearchTerm = normalizeString(searchTerm);
      
      return urunAdi.includes(searchTermLower) || 
             urunKodu.includes(searchTermLower) ||
             normalizeString(urunAdi).includes(normalizedSearchTerm) ||
             normalizeString(urunKodu).includes(normalizedSearchTerm);
    });
    
    // Sonuçları göster
    urunResults.innerHTML = '';
    
    if (filteredOptions.length === 0) {
      const noResult = document.createElement('div');
      noResult.className = 'p-2 text-sm text-gray-500';
      noResult.textContent = 'Sonuç bulunamadı';
      urunResults.appendChild(noResult);
    } else {
      filteredOptions.forEach(option => {
        const resultItem = document.createElement('div');
        resultItem.className = 'p-2 text-sm hover:bg-gray-100 cursor-pointer';
        resultItem.textContent = option.textContent;
        resultItem.dataset.id = option.value;
        resultItem.dataset.ad = option.getAttribute('data-ad');
        resultItem.dataset.kod = option.getAttribute('data-kod');
        resultItem.dataset.fiyat = option.getAttribute('data-fiyat');
        
        resultItem.addEventListener('click', function() {
          urunSelect.value = this.dataset.id;
          satir.querySelector('.urun-search').value = this.dataset.ad;
          satir.querySelector('.selected-urun').textContent = `${this.dataset.ad} (${this.dataset.kod})`;
          satir.querySelector('.selected-urun').classList.remove('hidden');
          urunResults.classList.add('hidden');
          
          // Fiyatı otomatik doldur
          const fiyat = this.dataset.fiyat || 0;
          satir.querySelector('.fiyat-input').value = parseFloat(fiyat).toFixed(2);
          
          hesaplaSatirToplam.call(satir.querySelector('.miktar-input'));
        });
        
        urunResults.appendChild(resultItem);
      });
    }
    
    urunResults.classList.remove('hidden');
  }
  
  // Ürün arama kutusundan çıkıldığında
  document.addEventListener('click', function(e) {
    document.querySelectorAll('.urun-results').forEach(results => {
      const searchInput = results.previousElementSibling;
      if (!searchInput.contains(e.target) && !results.contains(e.target)) {
        results.classList.add('hidden');
      }
    });
  });
  
  // Satır toplamını hesapla
  function hesaplaSatirToplam() {
    const satir = this.closest('tr');
    const miktar = parseFloat(satir.querySelector('.miktar-input').value) || 0;
    const birimFiyat = parseFloat(satir.querySelector('.fiyat-input').value) || 0;
    
    const toplam = miktar * birimFiyat;
    satir.querySelector('.satir-toplam').textContent = toplam.toFixed(2);
    
    hesaplaGenelToplam();
  }
  
  // Genel toplamı hesapla
  function hesaplaGenelToplam() {
    let araToplam = 0;
    
    document.querySelectorAll('.satir-toplam').forEach(element => {
      araToplam += parseFloat(element.textContent) || 0;
    });
    
    document.getElementById('araToplam').textContent = araToplam.toFixed(2);
    
    // İskonto hesapla
    const iskontoOran = parseFloat(document.getElementById('iskonto_oran').value) || 0;
    const iskontoTutar = parseFloat(document.getElementById('iskonto_tutar').value) || 0;
    
    let iskonto = 0;
    if (iskontoOran > 0) {
      iskonto = araToplam * (iskontoOran / 100);
      document.getElementById('iskonto_tutar').value = iskonto.toFixed(2);
    } else {
      iskonto = iskontoTutar;
    }
    
    document.getElementById('iskontoTutar').textContent = iskonto.toFixed(2);
    
    // Genel toplam (iskonto düşülmüş)
    const genelToplam = araToplam - iskonto;
    document.getElementById('genelToplam').textContent = genelToplam.toFixed(2);
  }
  
  // İskonto alanları için event listener'lar
  document.getElementById('iskonto_oran').addEventListener('input', function() {
    if (parseFloat(this.value) > 0) {
      document.getElementById('iskonto_tutar').value = '0';
    }
    hesaplaGenelToplam();
  });
  
  document.getElementById('iskonto_tutar').addEventListener('input', function() {
    if (parseFloat(this.value) > 0) {
      document.getElementById('iskonto_oran').value = '0';
    }
    hesaplaGenelToplam();
  });
  
  // Form gönderilmeden önce kontrol
  document.getElementById('alisFaturaForm').addEventListener('submit', function(e) {
    const musteriId = document.getElementById('musteri_id').value;
    
    if (!musteriId) {
      e.preventDefault();
      alert('Lütfen bir müşteri seçin.');
      return;
    }
    
    let urunSecildi = false;
    document.querySelectorAll('.urun-select').forEach(select => {
      if (select.value) {
        urunSecildi = true;
      }
    });
    
    if (!urunSecildi) {
      e.preventDefault();
      alert('Lütfen en az bir ürün seçin.');
      return;
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?> 
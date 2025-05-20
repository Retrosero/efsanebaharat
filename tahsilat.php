<?php
// tahsilat.php
require_once 'includes/db.php';
include 'includes/header.php'; // Soldaki menü + üst bar burada

$successMessage = '';
$errorMessage = '';
$musteriList = [];
try {
    // Müşterileri alfabetik sıraya göre getir (ad ve soyad'a göre)
    $stmt = $pdo->query("SELECT * FROM musteriler WHERE aktif = 1 ORDER BY ad ASC, soyad ASC");
    $musteriList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $errorMessage = "Hata: " . $e->getMessage();
    $musteriList = [];
}

// Kullanıcı ID ve evrak no tanımla
$kullaniciID = $_SESSION['kullanici_id'] ?? null;
$evrakNo = 'THS-' . date('Ymd') . '-' . rand(1000, 9999);

// Form post
$selectedMusteriID = isset($_GET['musteri_id']) ? $_GET['musteri_id'] : null;
if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $selectedMusteriID = $_POST['musteri_id']      ?? null;
    $tahsilatTuru      = $_POST['tahsilat_turu']   ?? 'nakit';
    $tutar             = floatval($_POST['tutar']  ?? 0);
    $islemTarihi       = $_POST['tarih']           ?? date('Y-m-d');
    
    // Yeni eklenen alanlar
    $banka_id          = null;
    $cek_senet_no      = null;
    $vade_tarihi       = null;
    $aciklama          = trim($_POST['aciklama']   ?? '');

    // Ödeme türüne göre banka_id değerini al
    if ($tahsilatTuru === 'kredi') {
        $banka_id = $_POST['kredi_banka_id'] ?? null;
    } elseif ($tahsilatTuru === 'havale') {
        $banka_id = $_POST['havale_banka_id'] ?? null;
    } elseif ($tahsilatTuru === 'cek') {
        $banka_id = $_POST['cek_banka_id'] ?? null;
        $cek_senet_no = $_POST['cek_no'] ?? null;
        $vade_tarihi = $_POST['cek_vade'] ?? null;
    } elseif ($tahsilatTuru === 'senet') {
        $cek_senet_no = $_POST['senet_no'] ?? null;
        $vade_tarihi = $_POST['senet_vade'] ?? null;
    }

    if($selectedMusteriID && $tutar > 0 && $kullaniciID){
        try {
            $pdo->beginTransaction();

            // Tahsilat kaydı            
            $stmtT = $pdo->prepare("
                INSERT INTO odeme_tahsilat (
                    islem_turu, musteri_id, tutar, odeme_turu, 
                    aciklama, islem_tarihi, evrak_no, kullanici_id, onay_durumu, onayli
                ) VALUES (
                    'tahsilat', :mid, :tutar, :odeme_turu, 
                    :aciklama, :tarih, :evrak_no, :kullanici_id, 'onaylandi', 1
                )
            ");

            $stmtT->execute([
                ':mid' => $selectedMusteriID,
                ':tutar' => $tutar,
                ':odeme_turu' => $tahsilatTuru,
                ':aciklama' => $aciklama,
                ':tarih' => $islemTarihi,
                ':evrak_no' => $evrakNo,
                ':kullanici_id' => $kullaniciID
            ]);

            $odeme_id = $pdo->lastInsertId();

            // Eğer çek veya senet ise detay tablosuna kaydet
            if($tahsilatTuru === 'cek' || $tahsilatTuru === 'senet'){
                $stmtDet = $pdo->prepare("
                    INSERT INTO odeme_detay (
                        odeme_id, banka_id, cek_senet_no, vade_tarihi
                    ) VALUES (
                        :odeme_id, :banka_id, :cek_senet_no, :vade_tarihi
                    )
                ");
                $stmtDet->execute([
                    ':odeme_id' => $odeme_id,
                    ':banka_id' => $banka_id,
                    ':cek_senet_no' => $cek_senet_no,
                    ':vade_tarihi' => $vade_tarihi
                ]);
            }

            // Doğrudan cari hesabı güncelle
            $stmtCari = $pdo->prepare("
                UPDATE musteriler 
                SET cari_bakiye = cari_bakiye - :tutar 
                WHERE id = :musteri_id
            ");
            $stmtCari->execute([
                ':tutar' => $tutar,
                ':musteri_id' => $selectedMusteriID
            ]);

            $pdo->commit();
            $successMessage = 'Tahsilat başarıyla kaydedildi.';

        } catch(Exception $ex){
            $pdo->rollBack();
            $errorMessage = "Kaydetme hatası: " . $ex->getMessage();
        }
    }
    else if (!$kullaniciID) {
        $errorMessage = "Hata: Oturum açmış bir kullanıcı bulunamadı. Lütfen tekrar giriş yapın.";
    }
    else if (!$selectedMusteriID) {
        $errorMessage = "Hata: Lütfen bir müşteri seçin.";
    }
    else if ($tutar <= 0) {
        $errorMessage = "Hata: Lütfen geçerli bir tutar girin.";
    }
}

// Seçili müşteri
$selectedMusteriName = 'Müşteri Seçiniz';
$cariBakiye = 0;
if($selectedMusteriID){
    $stmtOne = $pdo->prepare("SELECT * FROM musteriler WHERE id=:id");
    $stmtOne->execute([':id'=>$selectedMusteriID]);
    $rowM = $stmtOne->fetch(PDO::FETCH_ASSOC);
    if($rowM){
       $selectedMusteriName = $rowM['ad'] .' '. $rowM['soyad'];
       $cariBakiye = (float)$rowM['cari_bakiye'];
    }
}

// Son 5 tahsilat
$lastTahsilatlar = [];
if($selectedMusteriID){
    try {
        $stmtT = $pdo->prepare("
            SELECT 
                o.*, 
                m.ad, 
                m.soyad,
                od.banka_id,
                od.cek_senet_no,
                od.vade_tarihi,
                b.banka_adi
            FROM odeme_tahsilat o
            JOIN musteriler m ON o.musteri_id = m.id
            LEFT JOIN odeme_detay od ON o.id = od.odeme_id
            LEFT JOIN banka_listesi b ON od.banka_id = b.id
            WHERE o.musteri_id = :mid
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT 5
        ");
        $stmtT->execute([':mid' => $selectedMusteriID]);
        $lastTahsilatlar = $stmtT->fetchAll(PDO::FETCH_ASSOC);
    } catch(Exception $e){
        // ignore
    }
}
?>
<div class="p-4 sm:p-6">
  <?php if($successMessage): ?>
    <div class="bg-green-100 text-green-800 px-3 py-2 rounded mb-3">
      <?= htmlspecialchars($successMessage) ?>
    </div>
  <?php endif; ?>
  
  <?php if($errorMessage): ?>
    <div class="bg-red-100 text-red-800 px-3 py-2 rounded mb-3">
      <?= htmlspecialchars($errorMessage) ?>
    </div>
  <?php endif; ?>

  <div class="flex space-x-1 sm:space-x-2 mb-4 sm:mb-6">
    <button 
      id="tahsilat-tab" 
      class="tab-active flex-1 py-2 !rounded-button text-sm sm:text-base font-medium transition-all"
    >
      Tahsilat
    </button>
    <button 
      id="tediye-tab" 
      class="flex-1 py-2 !rounded-button text-sm sm:text-base font-medium bg-white text-gray-600 hover:bg-gray-100 transition-all"
    >
      Tediye
    </button>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 lg:gap-6">
    <!-- Form Alanı -->
    <div class="col-span-2">
      <div class="bg-white rounded-lg p-3 sm:p-4 shadow-sm mb-4 sm:mb-6">
        <h2 class="text-lg sm:text-xl font-semibold mb-4">Tahsilat Bilgileri</h2>
        <form method="POST" action="">
          <!-- Müşteri Seçimi -->
          <div class="mb-4">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Müşteri Seçimi</label>
            <div class="relative">
              <button 
                type="button"
                id="customer-select" 
                class="w-full text-left px-3 py-2 border border-gray-300 !rounded-button flex items-center justify-between text-xs sm:text-sm"
              >
                <span id="selected-customer"><?= htmlspecialchars($selectedMusteriName) ?></span>
                <i class="ri-arrow-down-s-line"></i>
              </button>
              <input type="hidden" name="musteri_id" id="musteri_id" value="<?= htmlspecialchars($selectedMusteriID ?? '') ?>">

              <div 
                id="customer-dropdown" 
                class="hidden absolute z-10 w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg"
              >
                <div class="p-2">
                  <input 
                    type="text"
                    id="customerSearchInput"
                    placeholder="Müşteri Ara..." 
                    class="w-full px-2 py-1.5 border border-gray-300 rounded-md mb-2 text-xs sm:text-sm"
                  >
                  <div class="max-h-40 overflow-y-auto" id="customerOptions">
                    <!-- JS dolduracak -->
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tahsilat Türü -->
          <div class="mb-4">
            <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Tahsilat Türü</label>
            <input type="hidden" name="tahsilat_turu" id="tahsilatTuru" value="nakit">
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-1 sm:gap-2">
              <button type="button" class="payment-type payment-type-active p-2 sm:p-3 border-2 rounded-lg text-center transition-all" data-type="nakit">
                <i class="ri-money-dollar-circle-line text-xl sm:text-2xl mb-1"></i>
                <div class="text-xs sm:text-sm">Nakit</div>
              </button>
              <button type="button" class="payment-type p-2 sm:p-3 border-2 border-gray-200 rounded-lg text-center transition-all" data-type="kredi">
                <i class="ri-bank-card-line text-xl sm:text-2xl mb-1"></i>
                <div class="text-xs sm:text-sm">Kredi Kartı</div>
              </button>
              <button type="button" class="payment-type p-2 sm:p-3 border-2 border-gray-200 rounded-lg text-center transition-all" data-type="havale">
                <i class="ri-bank-line text-xl sm:text-2xl mb-1"></i>
                <div class="text-xs sm:text-sm">Havale</div>
              </button>
              <button type="button" class="payment-type p-2 sm:p-3 border-2 border-gray-200 rounded-lg text-center transition-all" data-type="cek">
                <i class="ri-draft-line text-xl sm:text-2xl mb-1"></i>
                <div class="text-xs sm:text-sm">Çek</div>
              </button>
              <button type="button" class="payment-type p-2 sm:p-3 border-2 border-gray-200 rounded-lg text-center transition-all" data-type="senet">
                <i class="ri-file-list-3-line text-xl sm:text-2xl mb-1"></i>
                <div class="text-xs sm:text-sm">Senet</div>
              </button>
            </div>
          </div>

          <!-- Bilgi Alanları -->
          <div id="payment-details" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
              <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Tutar</label>
                <div class="relative">
                  <input 
                    type="text" 
                    name="tutar"
                    id="tutar"
                    placeholder="0,00" 
                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                  <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <span class="text-gray-500">₺</span>
                  </div>
                </div>
              </div>
              
              <div>
                <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Tarih</label>
                <input 
                  type="date" 
                  name="tarih"
                  value="<?= date('Y-m-d') ?>" 
                  class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                >
              </div>
            </div>
            
            <!-- Kredi Kartı Alanları -->
            <div id="kredi-fields" class="hidden space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Banka</label>
                  <select 
                    name="kredi_banka_id" 
                    id="kredi_banka_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                    <option value="">Banka Seçin</option>
                    <?php 
                    // Bankaları veritabanından çek
                    $bankalar = $pdo->query("SELECT * FROM banka_listesi WHERE durum = 1 ORDER BY banka_adi")->fetchAll();
                    foreach ($bankalar as $banka): 
                    ?>
                    <option value="<?= $banka['id'] ?>"><?= htmlspecialchars($banka['banka_adi']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- Havale Alanları -->
            <div id="havale-fields" class="hidden space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Banka</label>
                  <select 
                    name="havale_banka_id" 
                    id="havale_banka_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                    <option value="">Banka Seçin</option>
                    <?php foreach ($bankalar as $banka): ?>
                    <option value="<?= $banka['id'] ?>"><?= htmlspecialchars($banka['banka_adi']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            
            <!-- Çek Alanları -->
            <div id="cek-fields" class="hidden space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Banka</label>
                  <select 
                    name="cek_banka_id" 
                    id="cek_banka_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                    <option value="">Banka Seçin</option>
                    <?php foreach ($bankalar as $banka): ?>
                    <option value="<?= $banka['id'] ?>"><?= htmlspecialchars($banka['banka_adi']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Çek No</label>
                  <input 
                    type="text" 
                    name="cek_no"
                    placeholder="Çek numarası" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                </div>
                
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Vade Tarihi</label>
                  <input 
                    type="date" 
                    name="cek_vade"
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                </div>
              </div>
            </div>
            
            <!-- Senet Alanları -->
            <div id="senet-fields" class="hidden space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-4">
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Senet No</label>
                  <input 
                    type="text" 
                    name="senet_no"
                    placeholder="Senet numarası" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                </div>
                
                <div>
                  <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Vade Tarihi</label>
                  <input 
                    type="date" 
                    name="senet_vade"
                    class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
                  >
                </div>
              </div>
            </div>
            
            <!-- Açıklama -->
            <div>
              <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Açıklama</label>
              <textarea 
                name="aciklama"
                rows="2"
                placeholder="Opsiyonel açıklama" 
                class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary text-xs sm:text-sm"
              ></textarea>
            </div>
            
            <div class="pt-2">
              <button 
                type="submit"
                class="w-full py-2 bg-primary text-white rounded-button hover:bg-primary/90 transition-colors text-xs sm:text-sm"
              >
                Tahsilat Kaydet
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Sağ Taraf - Özet ve Son İşlemler -->
    <div class="col-span-1">
      <!-- Müşteri Bakiye Özeti -->
      <div class="bg-white rounded-lg p-3 sm:p-4 shadow-sm mb-4">
        <h3 class="font-medium text-sm sm:text-base mb-3">Müşteri Bakiye Özeti</h3>
        
        <?php if($selectedMusteriID): ?>
        <div class="space-y-2">
          <div class="flex justify-between items-center">
            <span class="text-xs sm:text-sm text-gray-600">Müşteri:</span>
            <span class="text-xs sm:text-sm font-medium"><?= htmlspecialchars($selectedMusteriName) ?></span>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-xs sm:text-sm text-gray-600">Cari Bakiye:</span>
            <span class="text-xs sm:text-sm font-medium <?= $cariBakiye > 0 ? 'text-red-600' : 'text-green-600' ?>">
              <?= number_format(abs($cariBakiye), 2, ',', '.') ?> ₺
              <?= $cariBakiye > 0 ? '(Borç)' : ($cariBakiye < 0 ? '(Alacak)' : '') ?>
            </span>
          </div>
        </div>
        <?php else: ?>
        <div class="text-xs sm:text-sm text-gray-500 italic">
          Bakiye bilgisi için lütfen müşteri seçin.
        </div>
        <?php endif; ?>
      </div>
      
      <!-- Son Tahsilatlar -->
      <div class="bg-white rounded-lg p-3 sm:p-4 shadow-sm">
        <h3 class="font-medium text-sm sm:text-base mb-3">Son Tahsilatlar</h3>
        
        <?php if($selectedMusteriID && !empty($lastTahsilatlar)): ?>
        <div class="space-y-2">
          <?php foreach($lastTahsilatlar as $tahsilat): ?>
          <div class="p-2 border border-gray-100 rounded hover:bg-gray-50">
            <div class="flex justify-between items-center">
              <span class="text-xs sm:text-sm font-medium"><?= date('d.m.Y', strtotime($tahsilat['islem_tarihi'])) ?></span>
              <span class="text-xs sm:text-sm font-medium text-green-600"><?= number_format($tahsilat['tutar'], 2, ',', '.') ?> ₺</span>
            </div>
            <div class="flex justify-between items-center mt-1">
              <span class="text-xs text-gray-500">
                <?php 
                switch($tahsilat['odeme_turu']) {
                  case 'nakit': echo 'Nakit'; break;
                  case 'kredi': echo 'Kredi Kartı'; break;
                  case 'havale': echo 'Havale/EFT'; break;
                  case 'cek': echo 'Çek'; break;
                  case 'senet': echo 'Senet'; break;
                  default: echo ucfirst($tahsilat['odeme_turu']);
                }
                
                if(isset($tahsilat['banka_adi']) && !empty($tahsilat['banka_adi'])) {
                  echo ' - ' . $tahsilat['banka_adi'];
                }
                ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php elseif($selectedMusteriID): ?>
        <div class="text-xs sm:text-sm text-gray-500 italic">
          Bu müşteriye ait tahsilat kaydı bulunamadı.
        </div>
        <?php else: ?>
        <div class="text-xs sm:text-sm text-gray-500 italic">
          Tahsilat geçmişi için lütfen müşteri seçin.
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
// Müşteri verilerini JSON formatında al
const musteriData = <?php echo json_encode($musteriList); ?>;

document.addEventListener('DOMContentLoaded', function(){
  // Tab menüsü
  const tahsilatTab = document.getElementById('tahsilat-tab');
  const tediyeTab = document.getElementById('tediye-tab');
  
  if (tahsilatTab && tediyeTab) {
    [tahsilatTab, tediyeTab].forEach(tab => {
      tab.addEventListener('click', () => {
        [tahsilatTab, tediyeTab].forEach(t => t.classList.remove('tab-active'));
        tab.classList.add('tab-active');
      });
    });
  }

  // Müşteri Seçimi
  const custSelectBtn = document.getElementById('customer-select');
  const custDropdown = document.getElementById('customer-dropdown');
  const custSearchInput = document.getElementById('customerSearchInput');
  const custOptionsDiv = document.getElementById('customerOptions');
  const hiddenMusteriID = document.getElementById('musteri_id');
  const selectedCustEl = document.getElementById('selected-customer');
  const cariBakiyeDisplay = document.getElementById('cari-bakiye');
  const selectedCustomerDisplay = document.getElementById('selected-customer-display');

  // Müşteri seçeneklerini render et
  function renderCustomerOptions(list) {
    if (!custOptionsDiv) return;
    
    custOptionsDiv.innerHTML = '';
    
    if (list.length === 0) {
      custOptionsDiv.innerHTML = '<div class="px-3 py-2 text-gray-500">Müşteri bulunamadı</div>';
      return;
    }
    
    list.forEach(musteri => {
      const option = document.createElement('div');
      option.className = 'customer-option px-3 py-2 hover:bg-gray-50 cursor-pointer rounded';
      option.dataset.id = musteri.id;
      option.textContent = `${musteri.ad} ${musteri.soyad || ''}`;
      
      option.addEventListener('click', () => {
        if (hiddenMusteriID) hiddenMusteriID.value = musteri.id;
        if (selectedCustEl) selectedCustEl.textContent = `${musteri.ad} ${musteri.soyad || ''}`;
        
        // Bakiye bilgisini güncelle
        if (cariBakiyeDisplay) {
          const bakiye = parseFloat(musteri.cari_bakiye || 0);
          cariBakiyeDisplay.textContent = `₺${bakiye.toLocaleString('tr-TR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
        }
        
        // Seçili müşteri bilgisini güncelle
        if (selectedCustomerDisplay) {
          selectedCustomerDisplay.textContent = `Seçili Müşteri: ${musteri.ad} ${musteri.soyad || ''}`;
        }
        
        if (custDropdown) custDropdown.classList.add('hidden');
        
        // Sayfayı yeniden yükle (müşteri ID'si ile)
        window.location.href = `tahsilat.php?musteri_id=${musteri.id}`;
      });
      
      custOptionsDiv.appendChild(option);
    });
  }

  // İlk yükleme
  if (musteriData && musteriData.length > 0) {
    renderCustomerOptions(musteriData);
  }

  // Müşteri seçim butonuna tıklama
  if (custSelectBtn) {
    custSelectBtn.addEventListener('click', () => {
      if (custDropdown) custDropdown.classList.toggle('hidden');
    });
  }

  // Dışarı tıklandığında dropdown'ı kapat
  document.addEventListener('click', (e) => {
    if (custSelectBtn && custDropdown && !custSelectBtn.contains(e.target) && !custDropdown.contains(e.target)) {
      custDropdown.classList.add('hidden');
    }
  });

  // Müşteri arama
  if (custSearchInput) {
    custSearchInput.addEventListener('input', (e) => {
      const searchText = e.target.value.toLowerCase().trim();
      
      if (!musteriData) return;
      
      const filteredCustomers = musteriData.filter(musteri => {
        const fullName = `${musteri.ad} ${musteri.soyad || ''}`.toLowerCase();
        return fullName.includes(searchText);
      });
      
      renderCustomerOptions(filteredCustomers);
    });
  }

  // Tahsilat Türü
  const payTypes = document.querySelectorAll('.payment-type');
  const tahsilatTuru = document.getElementById('tahsilatTuru');
  const krediFields = document.getElementById('kredi-fields');
  const havaleFields = document.getElementById('havale-fields');
  const cekFields = document.getElementById('cek-fields');
  const senetFields = document.getElementById('senet-fields');

  if (payTypes.length > 0) {
    payTypes.forEach(pt => {
      pt.addEventListener('click', () => {
        payTypes.forEach(x => x.classList.remove('payment-type-active'));
        pt.classList.add('payment-type-active');
        
        const payType = pt.dataset.type;
        if (tahsilatTuru) tahsilatTuru.value = payType;

        // Önce tüm alanları gizle
        if (krediFields) krediFields.classList.add('hidden');
        if (havaleFields) havaleFields.classList.add('hidden');
        if (cekFields) cekFields.classList.add('hidden');
        if (senetFields) senetFields.classList.add('hidden');

        // Seçime göre gerekli alanları göster
        switch(payType) {
          case 'kredi':
            if (krediFields) krediFields.classList.remove('hidden');
            break;
          case 'havale':
            if (havaleFields) havaleFields.classList.remove('hidden');
            break;
          case 'cek':
            if (cekFields) cekFields.classList.remove('hidden');
            break;
          case 'senet':
            if (senetFields) senetFields.classList.remove('hidden');
            break;
        }
      });
    });
  }
});
</script>

<?php
include 'includes/footer.php'; // menü kapanış + scriptler
?>

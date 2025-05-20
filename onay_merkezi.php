<?php
// onay_merkezi.php
require_once 'includes/db.php';
include 'includes/header.php';

// Yetki kontrolü
if (!sayfaErisimKontrol($pdo, 'onay_merkezi.php')) {
    $_SESSION['hata_mesaji'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
    header("Location: error.php");
    exit();
}

// İstek parametrelerini al
$durum = isset($_GET['durum']) ? $_GET['durum'] : 'bekliyor';
$tip = isset($_GET['tip']) ? $_GET['tip'] : 'all';
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// Onay işlem verilerini çek
$where_conditions = [];
$params = [];

// Durum filtreleme
if ($durum === 'bekliyor' || $durum === 'onaylandi' || $durum === 'reddedildi') {
    $where_conditions[] = "oi.durum = :durum";
    $params[':durum'] = $durum;
}

// İşlem tipi filtreleme
if ($tip !== 'all') {
    $where_conditions[] = "oi.islem_tipi = :tip";
    $params[':tip'] = $tip;
}

// Arama filtresi
if (!empty($arama)) {
    $where_conditions[] = "(oi.referans_no LIKE :arama OR m.ad LIKE :arama OR m.soyad LIKE :arama OR t.firma_adi LIKE :arama)";
    $params[':arama'] = "%{$arama}%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(' AND ', $where_conditions) : "";

// SQL sorgusu
$sql = "
    SELECT oi.*, 
           k_ekleyen.kullanici_adi AS ekleyen_kullanici,
           k_onaylayan.kullanici_adi AS onaylayan_kullanici,
           m.ad AS musteri_ad, 
           m.soyad AS musteri_soyad,
           t.firma_adi AS tedarikci_adi,
           CASE 
               WHEN oi.islem_tipi = 'satis' THEN f_satis.fatura_no
               WHEN oi.islem_tipi = 'alis' THEN f_alis.fatura_no
               WHEN oi.islem_tipi = 'tahsilat' THEN ot.evrak_no
               WHEN oi.islem_tipi = 'odeme' THEN ot.evrak_no
               ELSE oi.referans_no
           END AS belge_no
    FROM onay_islemleri oi
    LEFT JOIN kullanicilar k_ekleyen ON oi.ekleyen_id = k_ekleyen.id
    LEFT JOIN kullanicilar k_onaylayan ON oi.onaylayan_id = k_onaylayan.id
    LEFT JOIN musteriler m ON oi.musteri_id = m.id
    LEFT JOIN tedarikciler t ON oi.tedarikci_id = t.id
    LEFT JOIN faturalar f_satis ON oi.islem_id = f_satis.id AND oi.islem_tipi = 'satis'
    LEFT JOIN faturalar f_alis ON oi.islem_id = f_alis.id AND oi.islem_tipi = 'alis'
    LEFT JOIN odeme_tahsilat ot ON oi.islem_id = ot.id AND (oi.islem_tipi = 'tahsilat' OR oi.islem_tipi = 'odeme')
    {$where_clause}
    ORDER BY oi.created_at DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $onay_islemleri = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Hata: " . $e->getMessage();
    $onay_islemleri = [];
}

// Onay veya reddetme işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['islem']) && isset($_POST['id'])) {
        $islem = $_POST['islem'];
        $id = $_POST['id'];
        $not = isset($_POST['not']) ? $_POST['not'] : '';
        
        try {
            if ($islem === 'onayla') {
                $sql = "UPDATE onay_islemleri SET durum = 'onaylandi', onaylayan_id = :kullanici_id, onay_tarihi = NOW(), onay_notu = :not WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':kullanici_id' => $_SESSION['kullanici_id'],
                    ':not' => $not,
                    ':id' => $id
                ]);
                
                // İşlem verisini çek
                $sql_islem = "SELECT * FROM onay_islemleri WHERE id = :id";
                $stmt_islem = $pdo->prepare($sql_islem);
                $stmt_islem->execute([':id' => $id]);
                $islem_data = $stmt_islem->fetch(PDO::FETCH_ASSOC);
                
                if ($islem_data) {
                    // İşlem tipine göre cari hesaba yansıtma
                    if ($islem_data['islem_tipi'] === 'satis') {
                        // Satış faturasını müşteri cari hesabına borç olarak ekle
                        $sql_musteri = "UPDATE musteriler SET cari_bakiye = cari_bakiye + :tutar WHERE id = :musteri_id";
                        $stmt_musteri = $pdo->prepare($sql_musteri);
                        $stmt_musteri->execute([
                            ':tutar' => $islem_data['tutar'],
                            ':musteri_id' => $islem_data['musteri_id']
                        ]);
                    } elseif ($islem_data['islem_tipi'] === 'alis') {
                        // Alış faturasını tedarikçi cari hesabına borç olarak ekle
                        $sql_tedarikci = "UPDATE tedarikciler SET cari_bakiye = cari_bakiye + :tutar WHERE id = :tedarikci_id";
                        $stmt_tedarikci = $pdo->prepare($sql_tedarikci);
                        $stmt_tedarikci->execute([
                            ':tutar' => $islem_data['tutar'],
                            ':tedarikci_id' => $islem_data['tedarikci_id']
                        ]);
                    } elseif ($islem_data['islem_tipi'] === 'tahsilat') {
                        // Tahsilatı müşteri cari hesabına alacak olarak ekle
                        $sql_musteri = "UPDATE musteriler SET cari_bakiye = cari_bakiye - :tutar WHERE id = :musteri_id";
                        $stmt_musteri = $pdo->prepare($sql_musteri);
                        $stmt_musteri->execute([
                            ':tutar' => $islem_data['tutar'],
                            ':musteri_id' => $islem_data['musteri_id']
                        ]);
                    } elseif ($islem_data['islem_tipi'] === 'odeme') {
                        // Ödemeyi tedarikçi cari hesabına alacak olarak ekle
                        $sql_tedarikci = "UPDATE tedarikciler SET cari_bakiye = cari_bakiye - :tutar WHERE id = :tedarikci_id";
                        $stmt_tedarikci = $pdo->prepare($sql_tedarikci);
                        $stmt_tedarikci->execute([
                            ':tutar' => $islem_data['tutar'],
                            ':tedarikci_id' => $islem_data['tedarikci_id']
                        ]);
                    }
                }
                
                $_SESSION['success_message'] = "İşlem başarıyla onaylandı.";
            } elseif ($islem === 'reddet') {
                $sql = "UPDATE onay_islemleri SET durum = 'reddedildi', onaylayan_id = :kullanici_id, onay_tarihi = NOW(), onay_notu = :not WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':kullanici_id' => $_SESSION['kullanici_id'],
                    ':not' => $not,
                    ':id' => $id
                ]);
                
                $_SESSION['success_message'] = "İşlem reddedildi.";
            }
            
            // Sayfayı yenile
            header("Location: onay_merkezi.php?durum={$durum}&tip={$tip}&arama={$arama}");
            exit();
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Hata: " . $e->getMessage();
        }
    }
}
?>

<div class="flex flex-col h-screen">
  <div class="w-full bg-white shadow mb-6">
    <div class="p-4 border-b">
      <h1 class="text-xl font-bold text-gray-800">Onay Merkezi</h1>
      <p class="text-gray-600 mt-1">Onay bekleyen işlemleri görüntüleyin ve yönetin</p>
    </div>
    <div class="flex gap-4 px-4 border-b">
      <a href="?durum=bekliyor&tip=<?= $tip ?>&arama=<?= $arama ?>" class="py-3 px-3 border-b-2 <?= $durum === 'bekliyor' ? 'border-primary text-primary font-medium' : 'border-transparent text-gray-600' ?>">
        Bekleyen İşlemler
      </a>
      <a href="?durum=onaylandi&tip=<?= $tip ?>&arama=<?= $arama ?>" class="py-3 px-3 border-b-2 <?= $durum === 'onaylandi' ? 'border-primary text-primary font-medium' : 'border-transparent text-gray-600' ?>">
        Onaylanan İşlemler
      </a>
      <a href="?durum=reddedildi&tip=<?= $tip ?>&arama=<?= $arama ?>" class="py-3 px-3 border-b-2 <?= $durum === 'reddedildi' ? 'border-primary text-primary font-medium' : 'border-transparent text-gray-600' ?>">
        Reddedilen İşlemler
      </a>
    </div>
  </div>
  
  <div class="flex-1 overflow-hidden">
    <div class="p-6">
      <div class="flex justify-between items-center mb-6">
        <div class="flex gap-4">
          <select id="tipSelect" onchange="filterByType(this.value)" class="border border-gray-300 rounded-button px-3 py-2 text-sm">
            <option value="all" <?= $tip === 'all' ? 'selected' : '' ?>>Tüm İşlemler</option>
            <option value="satis" <?= $tip === 'satis' ? 'selected' : '' ?>>Satış İşlemleri</option>
            <option value="alis" <?= $tip === 'alis' ? 'selected' : '' ?>>Alış İşlemleri</option>
            <option value="tahsilat" <?= $tip === 'tahsilat' ? 'selected' : '' ?>>Tahsilat İşlemleri</option>
            <option value="odeme" <?= $tip === 'odeme' ? 'selected' : '' ?>>Ödeme İşlemleri</option>
          </select>
        </div>
        <div class="relative">
          <form action="" method="GET" class="flex items-center">
            <input type="hidden" name="durum" value="<?= $durum ?>">
            <input type="hidden" name="tip" value="<?= $tip ?>">
            <input type="text" name="arama" placeholder="Ara..." value="<?= htmlspecialchars($arama) ?>" class="pl-10 pr-4 py-2 border rounded-button focus:outline-none focus:border-primary">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <button type="submit" class="ml-2 bg-primary text-white px-4 py-2 rounded-button">Ara</button>
          </form>
        </div>
      </div>
  
      <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
          <?= $_SESSION['success_message'] ?>
          <?php unset($_SESSION['success_message']); ?>
        </div>
      <?php endif; ?>
  
      <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
          <?= $_SESSION['error_message'] ?>
          <?php unset($_SESSION['error_message']); ?>
        </div>
      <?php endif; ?>
  
      <div class="bg-white rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
          <table class="min-w-full">
            <thead>
              <tr class="bg-gray-50 border-b">
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem No</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlem Tipi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Müşteri/Tedarikçi</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tutar</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Açıklama</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarih</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Durum</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">İşlemler</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($onay_islemleri)): ?>
                <tr>
                  <td colspan="8" class="px-6 py-4 text-center text-gray-500">Gösterilecek işlem bulunamadı.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($onay_islemleri as $islem): ?>
                  <?php
                    // Durum sınıfları
                    $durum_class = '';
                    $durum_text = '';
                    
                    if ($islem['durum'] === 'bekliyor') {
                      $durum_class = 'bg-yellow-100 text-yellow-800';
                      $durum_text = 'Bekliyor';
                    } elseif ($islem['durum'] === 'onaylandi') {
                      $durum_class = 'bg-green-100 text-green-800';
                      $durum_text = 'Onaylandı';
                    } elseif ($islem['durum'] === 'reddedildi') {
                      $durum_class = 'bg-red-100 text-red-800';
                      $durum_text = 'Reddedildi';
                    }
                    
                    // İşlem tipi çevirisi
                    $islem_tipi_text = '';
                    if ($islem['islem_tipi'] === 'satis') {
                      $islem_tipi_text = 'Satış';
                    } elseif ($islem['islem_tipi'] === 'alis') {
                      $islem_tipi_text = 'Alış';
                    } elseif ($islem['islem_tipi'] === 'tahsilat') {
                      $islem_tipi_text = 'Tahsilat';
                    } elseif ($islem['islem_tipi'] === 'odeme') {
                      $islem_tipi_text = 'Ödeme';
                    }
                    
                    // Müşteri veya tedarikçi bilgisi
                    $firma_bilgisi = '';
                    if (!empty($islem['musteri_ad'])) {
                      $firma_bilgisi = $islem['musteri_ad'] . ' ' . $islem['musteri_soyad'];
                    } elseif (!empty($islem['tedarikci_adi'])) {
                      $firma_bilgisi = $islem['tedarikci_adi'];
                    }
                  ?>
                  <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($islem['belge_no'] ?? $islem['referans_no'] ?? '-') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= $islem_tipi_text ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= htmlspecialchars($firma_bilgisi) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= number_format($islem['tutar'], 2, ',', '.') ?> ₺</td>
                    <td class="px-6 py-4"><?= htmlspecialchars($islem['aciklama'] ?? '-') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= date('d.m.Y H:i', strtotime($islem['created_at'])) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $durum_class ?>">
                        <?= $durum_text ?>
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap space-x-2">
                      <button onclick="showDetails(<?= $islem['id'] ?>)" class="text-blue-600 hover:text-blue-900">
                        <i class="ri-eye-line"></i> Detay
                      </button>
                      <?php if ($islem['durum'] === 'bekliyor'): ?>
                        <button onclick="showApproveModal(<?= $islem['id'] ?>)" class="text-green-600 hover:text-green-900">
                          <i class="ri-check-line"></i> Onayla
                        </button>
                        <button onclick="showRejectModal(<?= $islem['id'] ?>)" class="text-red-600 hover:text-red-900">
                          <i class="ri-close-line"></i> Reddet
                        </button>
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
  </div>
</div>

<!-- Detay Modalı -->
<div id="detailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg w-[800px] max-h-[90vh] overflow-y-auto">
    <div class="border-b p-4 flex items-center justify-between">
      <h3 class="text-lg font-medium">İşlem Detayı</h3>
      <button onclick="closeModal('detailsModal')" class="text-gray-400 hover:text-gray-600">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
    <div class="p-6" id="detailContent">
      <!-- Detay içeriği dinamik olarak doldurulacak -->
      <p class="text-center text-gray-500">Yükleniyor...</p>
    </div>
  </div>
</div>

<!-- Onay Modalı -->
<div id="approveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg w-[500px]">
    <div class="border-b p-4 flex items-center justify-between">
      <h3 class="text-lg font-medium">İşlem Onayı</h3>
      <button onclick="closeModal('approveModal')" class="text-gray-400 hover:text-gray-600">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
    <form id="approveForm" method="POST">
      <input type="hidden" name="id" id="approveId">
      <input type="hidden" name="islem" value="onayla">
      <div class="p-6">
        <p class="mb-4">Bu işlemi onaylamak istediğinize emin misiniz?</p>
        <div class="mb-4">
          <label for="approveNote" class="block text-sm font-medium text-gray-700 mb-1">Onay Notu</label>
          <textarea id="approveNote" name="not" rows="3" class="w-full border border-gray-300 rounded-button px-3 py-2"></textarea>
        </div>
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-4">
          <p class="text-yellow-700 text-sm">
            <i class="ri-information-line mr-1"></i> Bu işlem onaylandığında ilgili cari hesaplara yansıtılacaktır.
          </p>
        </div>
        <div class="flex justify-end">
          <button type="button" onclick="closeModal('approveModal')" class="mr-2 px-4 py-2 border border-gray-300 rounded-button text-gray-700">İptal</button>
          <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-button">Onayla</button>
        </div>
      </div>
    </form>
  </div>
</div>

<!-- Reddetme Modalı -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-lg w-[500px]">
    <div class="border-b p-4 flex items-center justify-between">
      <h3 class="text-lg font-medium">İşlem Reddi</h3>
      <button onclick="closeModal('rejectModal')" class="text-gray-400 hover:text-gray-600">
        <i class="ri-close-line text-xl"></i>
      </button>
    </div>
    <form id="rejectForm" method="POST">
      <input type="hidden" name="id" id="rejectId">
      <input type="hidden" name="islem" value="reddet">
      <div class="p-6">
        <p class="mb-4">Bu işlemi reddetmek istediğinize emin misiniz?</p>
        <div class="mb-4">
          <label for="rejectNote" class="block text-sm font-medium text-gray-700 mb-1">Red Notu <span class="text-red-500">*</span></label>
          <textarea id="rejectNote" name="not" rows="3" class="w-full border border-gray-300 rounded-button px-3 py-2" required></textarea>
        </div>
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-4">
          <p class="text-red-700 text-sm">
            <i class="ri-information-line mr-1"></i> Reddedilen işlemler cari hesaplara yansıtılmayacaktır.
          </p>
        </div>
        <div class="flex justify-end">
          <button type="button" onclick="closeModal('rejectModal')" class="mr-2 px-4 py-2 border border-gray-300 rounded-button text-gray-700">İptal</button>
          <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-button">Reddet</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
// Modal açma/kapama fonksiyonları
function closeModal(modalId) {
  document.getElementById(modalId).classList.add('hidden');
  document.getElementById(modalId).classList.remove('flex');
}

function openModal(modalId) {
  document.getElementById(modalId).classList.remove('hidden');
  document.getElementById(modalId).classList.add('flex');
}

// İşlem tipine göre filtreleme fonksiyonu
function filterByType(type) {
  const url = new URL(window.location.href);
  url.searchParams.set('tip', type);
  window.location.href = url.toString();
}

// İşlem detaylarını göster
function showDetails(id) {
  openModal('detailsModal');
  document.getElementById('detailContent').innerHTML = '<p class="text-center text-gray-500">Yükleniyor...</p>';
  
  // AJAX isteği ile detayları getir
  fetch(`get_islem_detay.php?id=${id}`)
    .then(response => response.text())
    .then(data => {
      document.getElementById('detailContent').innerHTML = data;
    })
    .catch(error => {
      document.getElementById('detailContent').innerHTML = `<p class="text-center text-red-500">Hata: ${error.message}</p>`;
    });
}

// Onay modalını göster
function showApproveModal(id) {
  document.getElementById('approveId').value = id;
  openModal('approveModal');
}

// Red modalını göster
function showRejectModal(id) {
  document.getElementById('rejectId').value = id;
  openModal('rejectModal');
}

// Modallara dışarı tıklandığında kapat
document.addEventListener('click', function(e) {
  const modals = ['detailsModal', 'approveModal', 'rejectModal'];
  
  modals.forEach(modalId => {
    const modal = document.getElementById(modalId);
    if (modal && modal.classList.contains('flex')) {
      // Modal içeriğini bulma
      const modalContent = modal.querySelector('div.bg-white');
      // Eğer modalContent varsa ve tıklanan element modalContent içinde değilse
      if (modalContent && e.target !== modalContent && !modalContent.contains(e.target) && 
          // Ayrıca e.target modalın kendisi değilse
          e.target !== modal && 
          // Ve tıklanan yer modal açma butonlarından biri değilse kapat
          !e.target.closest('[onclick^="showDetails"]') && 
          !e.target.closest('[onclick^="showApproveModal"]') && 
          !e.target.closest('[onclick^="showRejectModal"]')) {
        closeModal(modalId);
      }
    }
  });
});
</script>

<?php include 'includes/footer.php'; ?>

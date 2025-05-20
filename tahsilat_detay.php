<?php
// tahsilat_detay.php

require_once 'includes/db.php';
include 'includes/header.php'; // Menüyü dahil eder (isterseniz ekleyin)

// ?id= parametresi => tahsilat_id
$tahsilat_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Veritabanından tahsilat bilgisi - SQL sorgusunu güncelle
$stmt = $pdo->prepare("
    SELECT 
        o.*, 
        m.ad AS musteri_ad, 
        m.soyad AS musteri_soyad, 
        m.vergi_no, 
        m.adres,
        od.banka_id,
        od.cek_senet_no,
        od.vade_tarihi,
        b.banka_adi
    FROM odeme_tahsilat o
    JOIN musteriler m ON o.musteri_id = m.id
    LEFT JOIN odeme_detay od ON o.id = od.odeme_id
    LEFT JOIN banka_listesi b ON od.banka_id = b.id
    WHERE o.id = :tid
");
$stmt->execute([':tid'=>$tahsilat_id]);
$tahsilat = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$tahsilat){
  echo "<p class='p-4 text-red-600'>Tahsilat bulunamadı.</p>";
  include 'includes/footer.php';
  exit;
}

// Örnek: "THS-2025-0125" format
$tahsilatNo = 'THS-' . date('Y') . '-' . str_pad($tahsilat_id,4,'0',STR_PAD_LEFT);
$odemeYontemi = $tahsilat['odeme_yontemi'] ?: 'Nakit';
$tarih = date('d.m.Y', strtotime($tahsilat['islem_tarihi'] ?? $tahsilat['created_at']));
$tutar = number_format($tahsilat['tutar'],2,',','.');
$aciklama = $tahsilat['aciklama'] ?? ''; // Açıklamayı veritabanından al
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tahsilat Detayları</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <!-- Tools: jsPDF, xlsx, html2canvas -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://html2canvas.hertzen.com/dist/html2canvas.min.js"></script>

  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#3176FF',
            secondary: '#6B7280'
          },
          borderRadius: {
            'none': '0px',
            'sm': '4px',
            DEFAULT: '8px',
            'md': '12px',
            'lg': '16px',
            'xl': '20px',
            '2xl': '24px',
            '3xl': '32px',
            'full': '9999px',
            'button': '8px'
          }
        }
      }
    }
  </script>
  <style>
    @media print {
      .print\:hidden, .no-print, button, .button, input, select, .actions, .action-buttons {
        display: none !important;
      }
      body {
        background-color: white !important;
      }
      .print-only {
        display: block !important;
      }
    }
    :where([class^="ri-"])::before { content: "\f3c2"; }
  </style>
</head>
<body class="bg-gray-50 min-h-screen">

<!-- Geri butonu -->
<div class="flex justify-between items-center mb-6">
  <div class="flex space-x-2">
    <button 
      id="backButton"
      class="flex items-center px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
    >
      <i class="ri-arrow-left-line mr-2"></i> Geri
    </button>
  </div>
  
  <div class="flex space-x-2">
    <!-- Düzenle Butonu -->
    <a 
      href="tahsilat_duzenle.php?id=<?= $tahsilat_id ?>" 
      class="flex items-center px-4 py-2 bg-blue-50 text-primary hover:bg-blue-100 rounded-button text-sm"
    >
      <i class="ri-edit-line mr-2"></i> Düzenle
    </a>
    
    <!-- Sil Butonu -->
    <button 
      type="button"
      id="deleteButton"
      class="flex items-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-button text-sm"
    >
      <i class="ri-delete-bin-line mr-2"></i> Sil
    </button>
    
    <!-- Yazdır Butonu -->
    <button 
      id="printButton"
      class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm"
    >
      <i class="ri-printer-line mr-2"></i> Yazdır
    </button>
  </div>
</div>

<!-- Silme Onay Modalı -->
<div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
  <div class="bg-white rounded-lg p-6 w-full max-w-md">
    <h3 class="text-lg font-medium text-gray-900 mb-4">Tahsilatı Sil</h3>
    <p class="text-gray-500 mb-6">Bu tahsilatı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
    <div class="flex justify-end space-x-3">
      <button 
        type="button" 
        id="cancelDeleteBtn"
        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-button text-sm"
      >
        İptal
      </button>
      <button 
        type="button" 
        id="confirmDeleteBtn"
        class="px-4 py-2 bg-red-600 text-white rounded-button text-sm"
      >
        Sil
      </button>
    </div>
  </div>
</div>

<div class="container mx-auto px-4 py-6">
  <div class="bg-white rounded-lg shadow-sm p-6">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Tahsilat Detayı</h1>
      <div class="text-sm text-gray-500"><?= $tarih ?></div>
    </div>
    
    <!-- Arama ve Yazdırma Butonları -->
    <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-2 print:hidden">
      <div class="relative w-full sm:w-64">
        <input
          type="text"
          id="tahsilatDetaySearch"
          placeholder="Ara..."
          class="w-full px-4 py-2 pr-10 border rounded-lg focus:outline-none focus:border-primary text-sm"
        >
        <i class="ri-search-line absolute right-3 top-2.5 text-gray-400"></i>
      </div>
      <button 
        id="printButton2"
        class="flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg text-sm"
      >
        <i class="ri-printer-line mr-2"></i> Yazdır
      </button>
    </div>

    <div id="printArea" class="space-y-6">
      <!-- Üst Bilgiler -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="space-y-3">
          <div class="flex justify-between p-3 bg-gray-50 rounded">
            <span class="text-gray-600">Tahsilat No</span>
            <span class="font-medium"><?= htmlspecialchars($tahsilatNo) ?></span>
          </div>
          <div class="flex justify-between p-3 bg-gray-50 rounded">
            <span class="text-gray-600">Tarih</span>
            <span class="font-medium"><?= htmlspecialchars($tarih) ?></span>
          </div>
        </div>
        <div class="space-y-3">
          <div class="flex justify-between p-3 bg-gray-50 rounded">
            <span class="text-gray-600">Tutar</span>
            <span class="font-medium"><?= $tutar ?> ₺</span>
          </div>
          <div class="flex justify-between p-3 bg-gray-50 rounded">
            <span class="text-gray-600">Ödeme Yöntemi</span>
            <span class="font-medium"><?= htmlspecialchars($odemeYontemi) ?></span>
          </div>
        </div>
      </div>

      <!-- Ödeme Detayları -->
      <?php if (in_array($tahsilat['odeme_yontemi'], ['cek', 'senet', 'havale', 'kredi'])): ?>
      <div class="border-t pt-6">
          <h2 class="text-lg font-medium mb-4">Ödeme Detayları</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <?php if ($tahsilat['banka_adi']): ?>
              <div class="p-3 bg-gray-50 rounded">
                  <div class="text-gray-600 mb-1">Banka</div>
                  <div class="font-medium">
                      <?= htmlspecialchars($tahsilat['banka_adi']) ?>
                  </div>
              </div>
              <?php endif; ?>

              <?php if ($tahsilat['cek_senet_no']): ?>
              <div class="p-3 bg-gray-50 rounded">
                  <div class="text-gray-600 mb-1">
                      <?= $tahsilat['odeme_yontemi'] === 'cek' ? 'Çek No' : 'Senet No' ?>
                  </div>
                  <div class="font-medium">
                      <?= htmlspecialchars($tahsilat['cek_senet_no']) ?>
                  </div>
              </div>
              <?php endif; ?>

              <?php if ($tahsilat['vade_tarihi']): ?>
              <div class="p-3 bg-gray-50 rounded">
                  <div class="text-gray-600 mb-1">Vade Tarihi</div>
                  <div class="font-medium">
                      <?= date('d.m.Y', strtotime($tahsilat['vade_tarihi'])) ?>
                  </div>
              </div>
              <?php endif; ?>
          </div>
      </div>
      <?php endif; ?>

      <!-- Müşteri Bilgileri -->
      <div class="border-t pt-6">
        <h2 class="text-lg font-medium mb-4">Müşteri Bilgileri</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded">
              <div class="text-gray-600 mb-1">Ad Soyad</div>
              <div class="font-medium">
                <?= htmlspecialchars($tahsilat['musteri_ad'].' '.$tahsilat['musteri_soyad']) ?>
              </div>
            </div>
            <div class="p-3 bg-gray-50 rounded">
              <div class="text-gray-600 mb-1">TC/Vergi No</div>
              <div class="font-medium">
                <?= htmlspecialchars($tahsilat['vergi_no'] ?? '-----') ?>
              </div>
            </div>
          </div>
          <div class="space-y-3">
            <div class="p-3 bg-gray-50 rounded">
              <div class="text-gray-600 mb-1">Adres</div>
              <div class="font-medium">
                <?= htmlspecialchars($tahsilat['adres'] ?? 'Adres Yok') ?>
              </div>
            </div>
            <div class="p-3 bg-gray-50 rounded">
              <div class="text-gray-600 mb-1">Açıklama</div>
              <div class="font-medium">
                <?= htmlspecialchars($aciklama) ?>
              </div>
            </div>
          </div>
        </div>
      </div> 
    </div><!-- /printArea -->
  </div>
</div>

<script>
// Güvenli bir şekilde PHP değişkenlerini JavaScript'e aktaralım
const tahsilatData = {
  id: <?php echo (int)$tahsilat_id; ?>,
  no: <?php echo json_encode($tahsilatNo); ?>,
  tarih: <?php echo json_encode($tarih); ?>,
  tutar: <?php echo json_encode($tutar); ?>,
  odemeYontemi: <?php echo json_encode($odemeYontemi); ?>,
  musteri: <?php echo json_encode($tahsilat['musteri_ad'].' '.$tahsilat['musteri_soyad']); ?>,
  vergiNo: <?php echo json_encode($tahsilat['vergi_no'] ?? '-----'); ?>,
  adres: <?php echo json_encode($tahsilat['adres'] ?? 'Adres Yok'); ?>,
  aciklama: <?php echo json_encode($aciklama); ?>
};

// Sayfa yüklendiğinde çalışacak kodlar
document.addEventListener('DOMContentLoaded', function() {
  // DOM elementlerini al
  const deleteModal = document.getElementById('deleteModal');
  const deleteButton = document.getElementById('deleteButton');
  const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
  const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
  const backButton = document.getElementById('backButton');
  const printButton = document.getElementById('printButton');
  const printButton2 = document.getElementById('printButton2');
  const searchInput = document.getElementById('tahsilatDetaySearch');
  
  // Geri butonu
  if (backButton) {
    backButton.addEventListener('click', function() {
      history.back();
    });
  }
  
  // Yazdır butonları
  if (printButton) {
    printButton.addEventListener('click', function() {
      window.print();
    });
  }
  
  if (printButton2) {
    printButton2.addEventListener('click', function() {
      window.print();
  });
}

  // Silme işlemleri
  if (deleteButton && deleteModal && confirmDeleteBtn && cancelDeleteBtn) {
    // Silme butonuna event listener ekle
    deleteButton.addEventListener('click', function() {
      deleteModal.classList.remove('hidden');
    });
    
    // Silme işlemi
    confirmDeleteBtn.addEventListener('click', function() {
      const formData = new FormData();
      formData.append('islem', 'tahsilat_sil');
      formData.append('id', tahsilatData.id);
      
  fetch('ajax_islem.php', {
    method: 'POST',
        body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
          alert('Tahsilat başarıyla silindi.');
          window.location.href = 'tahsilat.php';
    } else {
          alert('Hata: ' + (data.message || 'Bilinmeyen bir hata oluştu'));
          deleteModal.classList.add('hidden');
    }
  })
  .catch(error => {
        console.error('Silme hatası:', error);
    alert('İşlem sırasında bir hata oluştu.');
        deleteModal.classList.add('hidden');
      });
    });
    
    // İptal butonu
    cancelDeleteBtn.addEventListener('click', function() {
      deleteModal.classList.add('hidden');
  });

    // Modal dışına tıklandığında kapat
    deleteModal.addEventListener('click', function(e) {
      if (e.target === deleteModal) {
        deleteModal.classList.add('hidden');
  }
});

    // ESC tuşu ile modal kapatma
document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && !deleteModal.classList.contains('hidden')) {
        deleteModal.classList.add('hidden');
  }
});
  }

  // Arama fonksiyonu
  if (searchInput) {
    searchInput.addEventListener('keyup', function() {
      const searchTerm = this.value.toLowerCase();
      const printArea = document.getElementById('printArea');
      
      if (printArea) {
        const searchableAreas = printArea.querySelectorAll('.p-3');
        searchableAreas.forEach(area => {
          const text = area.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            area.style.backgroundColor = '#FFFDE7'; // Hafif sarı vurgu
          } else {
            area.style.backgroundColor = '#f9fafb'; // Normal renk
          }
      });
      }
    });
  }
});

// PDF, Excel ve PNG fonksiyonları
function savePDF() {
  const printArea = document.getElementById('printArea');
  if (!printArea || !window.jspdf) return;
  
  try {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    html2canvas(printArea).then(canvas => {
      const imgData = canvas.toDataURL('image/png');
      const imgWidth = 210; // A4 - 210mm
      const pageHeight = 295;
      const imgHeight = canvas.height * imgWidth / canvas.width;
      let position = 0;
      doc.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      doc.save('tahsilat-detay.pdf');
    });
  } catch(e) {
    console.error('PDF oluşturma hatası:', e);
  }
}

function saveExcel() {
  if (!window.XLSX) return;
  
  try {
    const data = [
      ['Tahsilat No', tahsilatData.no],
      ['Tarih', tahsilatData.tarih],
      ['Tutar', tahsilatData.tutar + '₺'],
      ['Ödeme Yöntemi', tahsilatData.odemeYontemi],
      ['Ad Soyad', tahsilatData.musteri],
      ['TC/Vergi No', tahsilatData.vergiNo],
      ['Adres', tahsilatData.adres]
    ];
    
    <?php if(isset($tahsilat['banka_adi']) && $tahsilat['banka_adi']): ?>
    data.push(['Banka', <?php echo json_encode($tahsilat['banka_adi']); ?>]);
    <?php endif; ?>
    
    <?php if(isset($tahsilat['cek_senet_no']) && $tahsilat['cek_senet_no']): ?>
    data.push([
      <?php echo json_encode($tahsilat['odeme_yontemi'] === 'cek' ? 'Çek No' : 'Senet No'); ?>, 
      <?php echo json_encode($tahsilat['cek_senet_no']); ?>
    ]);
    <?php endif; ?>
    
    <?php if(isset($tahsilat['vade_tarihi']) && $tahsilat['vade_tarihi']): ?>
    data.push(['Vade Tarihi', <?php echo json_encode(date('d.m.Y', strtotime($tahsilat['vade_tarihi']))); ?>]);
    <?php endif; ?>
    
    data.push(['Açıklama', tahsilatData.aciklama]);
    
    const ws = XLSX.utils.aoa_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Tahsilat Detay');
    XLSX.writeFile(wb, 'tahsilat-detay.xlsx');
  } catch(e) {
    console.error('Excel oluşturma hatası:', e);
  }
}

function savePNG() {
  const printArea = document.getElementById('printArea');
  if (!printArea) return;
  
  try {
    html2canvas(printArea).then(canvas => {
      const link = document.createElement('a');
      link.download = 'tahsilat-detay.png';
      link.href = canvas.toDataURL();
      link.click();
    });
  } catch(e) {
    console.error('PNG oluşturma hatası:', e);
  }
}
</script>
</body>
</html>

<?php include 'includes/footer.php'; ?>

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
      onclick="history.back()" 
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
      onclick="showDeleteModal(<?= $tahsilat_id ?>)"
      class="flex items-center px-4 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded-button text-sm"
    >
      <i class="ri-delete-bin-line mr-2"></i> Sil
    </button>
    
    <!-- Yazdır Butonu -->
    <button 
      onclick="window.print()" 
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
        onclick="closeDeleteModal()"
        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-button text-sm"
      >
        İptal
      </button>
      <button 
        type="button" 
        onclick="deleteTahsilat()"
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
        onclick="window.print()"
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
const saveButton=document.getElementById('saveButton');
const saveOptions=document.getElementById('saveOptions');
saveButton.addEventListener('click',()=>{
  saveOptions.classList.toggle('hidden');
});
document.addEventListener('click',(e)=>{
  if(!saveButton.contains(e.target) && !saveOptions.contains(e.target)){
    saveOptions.classList.add('hidden');
  }
});

// PDF
function savePDF(){
  const { jsPDF }=window.jspdf;
  const doc=new jsPDF();
  html2canvas(document.getElementById('printArea')).then(canvas=>{
    const imgData=canvas.toDataURL('image/png');
    const imgWidth=210; // A4 - 210mm
    const pageHeight=295;
    const imgHeight=canvas.height*imgWidth/canvas.width;
    let position=0;
    doc.addImage(imgData,'PNG',0,position,imgWidth,imgHeight);
    doc.save('tahsilat-detay.pdf');
  });
}

// Excel
function saveExcel(){
    const data = [
        ['Tahsilat No', '<?= $tahsilatNo ?>'],
        ['Tarih', '<?= $tarih ?>'],
        ['Tutar', '<?= $tutar ?>₺'],
        ['Ödeme Yöntemi', '<?= htmlspecialchars($odemeYontemi) ?>'],
        ['Ad Soyad', '<?= htmlspecialchars($tahsilat['musteri_ad'].' '.$tahsilat['musteri_soyad']) ?>'],
        ['TC/Vergi No', '<?= htmlspecialchars($tahsilat['vergi_no'] ?? '-----') ?>'],
        ['Adres', '<?= htmlspecialchars($tahsilat['adres'] ?? 'Adres Yok') ?>']
    ];

    <?php if($tahsilat['banka_adi']): ?>
    data.push(['Banka', '<?= htmlspecialchars($tahsilat['banka_adi']) ?>']);
    <?php endif; ?>

    <?php if($tahsilat['cek_senet_no']): ?>
    data.push(['<?= $tahsilat['odeme_yontemi'] === 'cek' ? 'Çek No' : 'Senet No' ?>', 
               '<?= htmlspecialchars($tahsilat['cek_senet_no']) ?>']);
    <?php endif; ?>

    <?php if($tahsilat['vade_tarihi']): ?>
    data.push(['Vade Tarihi', '<?= date('d.m.Y', strtotime($tahsilat['vade_tarihi'])) ?>']);
    <?php endif; ?>

    data.push(['Açıklama', '<?= htmlspecialchars($aciklama) ?>']);

    const ws = XLSX.utils.aoa_to_sheet(data);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Tahsilat Detay');
    XLSX.writeFile(wb, 'tahsilat-detay.xlsx');
}

// PNG
function savePNG(){
  html2canvas(document.getElementById('printArea')).then(canvas=>{
    const link=document.createElement('a');
    link.download='tahsilat-detay.png';
    link.href=canvas.toDataURL();
    link.click();
  });
}

// Silme işlemleri için JavaScript
let tahsilatIdToDelete = 0;

function showDeleteModal(id) {
  tahsilatIdToDelete = id;
  document.getElementById('deleteModal').classList.remove('hidden');
}

function closeDeleteModal() {
  document.getElementById('deleteModal').classList.add('hidden');
}

function deleteTahsilat() {
  // AJAX ile silme işlemi
  fetch('ajax_islem.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: `islem=tahsilat_sil&id=${tahsilatIdToDelete}`
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Silme başarılı - önceki sayfaya dön
      window.location.href = document.referrer;
    } else {
      // Hata durumunda alert göster
      alert('Hata: ' + data.message);
      closeDeleteModal();
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('İşlem sırasında bir hata oluştu.');
    closeDeleteModal();
  });
}

// Modal dışına tıklandığında kapatma
document.getElementById('deleteModal').addEventListener('click', function(e) {
  if (e.target === this) {
    closeDeleteModal();
  }
});

// ESC tuşu ile modalı kapatma
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeDeleteModal();
  }
});

// Sayfa yüklendiğinde
document.addEventListener('DOMContentLoaded', function() {
  // Arama fonksiyonu
  const searchInput = document.getElementById('tahsilatDetaySearch');
  if (searchInput) {
    searchInput.addEventListener('keyup', function() {
      const searchTerm = this.value.toLowerCase();
      const tableRows = document.querySelectorAll('table tbody tr');
      
      tableRows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
      });
    });
  }
});
</script>
</body>
</html>

<?php include 'includes/footer.php'; ?>

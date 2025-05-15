<?php
// error.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Kullanıcı giriş yapmamışsa login sayfasına yönlendir
if (!girisYapmisMi()) {
    header('Location: login.php');
    exit();
}

// Hata mesajı
$hata_mesaji = $_SESSION['hata_mesaji'] ?? 'Bu sayfaya erişim yetkiniz bulunmamaktadır.';
unset($_SESSION['hata_mesaji']);

// Sayfa başlığı
$sayfa_basligi = 'Erişim Reddedildi';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <title><?= $sayfa_basligi ?> - Efsane Baharat</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <style>
    body { 
      font-family: 'Inter', sans-serif;
      background-color: #f8f8f8;
    }
  </style>
  
  <!-- TailwindCSS Primary Renk Tanımlaması -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#3176FF'
          }
        }
      }
    }
  </script>
</head>
<body class="bg-gray-50">
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-sm">
      <div class="text-center">
        <i class="ri-error-warning-line text-red-500 text-6xl"></i>
        <h2 class="mt-4 text-center text-2xl font-bold text-gray-900">
          <?= $sayfa_basligi ?>
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          <?= htmlspecialchars($hata_mesaji) ?>
        </p>
      </div>
      
      <div class="mt-6">
        <a href="index.php" 
          class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
          <span class="absolute left-0 inset-y-0 flex items-center pl-3">
            <i class="ri-home-line"></i>
          </span>
          Ana Sayfaya Dön
        </a>
      </div>
      
      <div class="mt-4">
        <a href="javascript:history.back()" 
          class="group relative w-full flex justify-center py-2 px-4 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
          <span class="absolute left-0 inset-y-0 flex items-center pl-3">
            <i class="ri-arrow-left-line"></i>
          </span>
          Geri Dön
        </a>
      </div>
    </div>
  </div>
</body>
</html> 
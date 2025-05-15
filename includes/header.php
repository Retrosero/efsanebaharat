<?php
// includes/header.php

// Oturum başlatma kontrolü
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Önbellek kontrolü
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Veritabanı ve auth dosyalarını dahil et
require_once 'includes/db.php';
require_once 'includes/auth.php';

// GEÇİCİ: Tüm sayfaları public yapmak için kontrolleri devre dışı bıraktık
// Aşağıdaki yorum satırlarını kaldırarak güvenlik kontrollerini tekrar aktifleştirebilirsiniz

/*
// Giriş gerektirmeyen sayfalar
$public_pages = ['giris.php', 'cikis.php', 'error.php'];

// Mevcut sayfa
$current_page = basename($_SERVER['PHP_SELF']);

// Eğer sayfa public değilse ve kullanıcı giriş yapmamışsa
if (!in_array($current_page, $public_pages)) {
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: giris.php');
        exit;
    }
    
    // Sayfa erişim kontrolü
    if (!sayfaErisimKontrol($pdo, $current_page)) {
        $_SESSION['hata_mesaji'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
        header("Location: error.php");
        exit();
    }
}

// Giriş kontrolü
girisGerekli();

// Login ve error sayfaları için kontrol yapma
$exempt_pages = ['login.php', 'logout.php', 'error.php'];

// Muaf sayfalar dışında oturum kontrolü yap
if (!in_array($current_page, $exempt_pages)) {
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: login.php');
        exit;
    }
}
*/

// GEÇİCİ: Admin rolünü otomatik olarak atama (sayfaların düzgün çalışması için)
if (!isset($_SESSION['kullanici_id'])) {
    $_SESSION['kullanici_id'] = 1; // Admin ID
    $_SESSION['kullanici_adi'] = 'Geçici Admin';
    $_SESSION['rol_id'] = 1; // Admin rolü
}

// Giriş gerektirmeyen sayfalar
$public_pages = ['giris.php', 'cikis.php', 'error.php'];

// Mevcut sayfa
$current_page = basename($_SERVER['PHP_SELF']);

// Eğer sayfa public değilse ve kullanıcı giriş yapmamışsa
if (!in_array($current_page, $public_pages)) {
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: giris.php');
        exit;
    }
    
    // Sayfa erişim kontrolü
    if (!sayfaErisimKontrol($pdo, $current_page)) {
        $_SESSION['hata_mesaji'] = "Bu sayfaya erişim yetkiniz bulunmamaktadır.";
        header("Location: error.php");
        exit();
    }
}

// Giriş kontrolü
girisGerekli();

// Login ve error sayfaları için kontrol yapma
$exempt_pages = ['login.php', 'logout.php', 'error.php'];

// Muaf sayfalar dışında oturum kontrolü yap
if (!in_array($current_page, $exempt_pages)) {
    if (!isset($_SESSION['kullanici_id'])) {
        header('Location: login.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <title>Efsane Baharat</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/echarts/5.5.0/echarts.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
  <style>
    :where([class^="ri-"])::before { content: "\f3c2"; }
    body { font-family: 'Inter', sans-serif; }
    
    /* Tooltip stil */
    .tooltip {
      position: relative;
    }
    .tooltip:hover:after {
      content: attr(data-tooltip);
      position: absolute;
      left: 100%;
      top: 50%;
      transform: translateY(-50%);
      background: #1a1a1a;
      color: white;
      padding: 4px 8px;
      border-radius: 4px;
      font-size: 12px;
      white-space: nowrap;
      margin-left: 10px;
      z-index: 1000;
    }
    #sideNav:not(.collapsed) .tooltip:hover:after {
      display: none;
    }
    
    /* Modern Scrollbar Stili */
    #sideNav .flex-1::-webkit-scrollbar {
      width: 6px;
    }
    
    #sideNav .flex-1::-webkit-scrollbar-track {
      background: rgba(255, 255, 255, 0.1);
      border-radius: 10px;
    }
    
    #sideNav .flex-1::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.3);
      border-radius: 10px;
    }
    
    #sideNav .flex-1::-webkit-scrollbar-thumb:hover {
      background: rgba(255, 255, 255, 0.5);
    }
    
    /* Firefox için scrollbar stili */
    #sideNav .flex-1 {
      scrollbar-width: thin;
      scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
    }

    /* Mobil cihazlar için responsive navbar ayarları */
    @media (max-width: 768px) {
      /* Mobil görünümde sol menü */
      #sideNav {
        height: auto;
        width: 100% !important;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
      }
      
      /* Mobil görünümde logo ve toggle butonu gizle */
      #sideNav .border-b {
        display: none;
      }
      
      /* Mobil görünümde menü içeriği */
      #sideNav .flex-1 {
        display: none;
      }
      
      /* Mobil görünümde profil kısmı */
      #sideNav .border-t {
        border-top: none;
      }
      
      /* Mobil görünümde ana içerik */
      main {
        padding-bottom: 60px; /* Mobil menü yüksekliği kadar alt boşluk */
        padding-top: 0; /* Üst boşluğu kaldır */
      }
      
      /* Mobil menü açıldığında */
      #sideNav.mobile-open .flex-1 {
        display: block;
        position: absolute;
        bottom: 60px;
        left: 0;
        width: 100%;
        background-color: #3176FF;
        max-height: 80vh;
        overflow-y: auto;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
      }
      
      /* Mobil menü kaydırma özelliği */
      .mobile-nav {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
        white-space: nowrap;
        padding: 0.5rem 0;
      }
      
      .mobile-nav::-webkit-scrollbar {
        height: 3px;
      }
      
      .mobile-nav::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
      }
      
      .mobile-nav::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
      }
      
      /* Sayfa içeriğinin bottom navigator alanı kadar yukarıdan başlaması */
      .flex-1.overflow-auto {
        padding-top: 0 !important;
        margin-bottom: 60px; /* Bottom navigator yüksekliği */
      }
    }
    
    /* Menü collapsed olduğunda başlıkları gizle */
    #sideNav.collapsed .nav-text,
    #sideNav.collapsed .logo-text {
      display: none;
    }
    
    /* Menü collapsed olduğunda genişliği küçült */
    #sideNav.collapsed {
      width: 4rem !important;
    }
    
    /* Menü geniş olduğunda yumuşak geçiş */
    #sideNav {
      transition: width 0.3s ease;
    }
    
    /* Profil kısmı için tooltip */
    #sideNav.collapsed #userMenuBtn {
      justify-content: center;
      padding: 0.5rem;
    }
    
    /* Profil menüsü için collapsed durumda pozisyon ayarı */
    #sideNav.collapsed #userMenu {
      left: 100%;
      bottom: 0;
      margin-bottom: 0;
      margin-left: 0.5rem;
    }
    
    /* Mobil menü için alt navigasyon */
    @media (max-width: 768px) {
      .mobile-nav {
        display: flex;
        justify-content: flex-start; /* Başlangıçta sola hizalı */
        padding: 0.5rem;
      }
      
      .mobile-nav a {
        display: flex;
        flex-direction: column;
        align-items: center;
        font-size: 0.75rem;
        color: rgba(255, 255, 255, 0.8);
        padding: 0 0.75rem;
        flex-shrink: 0;
      }
      
      .mobile-nav a i {
        font-size: 1.25rem;
        margin-bottom: 0.25rem;
      }
      
      .mobile-nav a.active {
        color: white;
      }
      
      /* Scrollbar thin */
      .scrollbar-thin::-webkit-scrollbar {
        height: 3px;
      }
      
      .scrollbar-thin::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
      }
      
      .scrollbar-thin::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
      }
    }
  </style>
  
  <!-- Tailwind Config -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: '#3176FF',
            secondary: '#48BB78'
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
</head>
<body class="bg-gray-50">

<!-- Üst Menü / Sidebar -->
<div class="flex h-screen flex-col md:flex-row">
  <nav id="sideNav" class="w-full md:w-64 bg-primary text-white flex flex-col transition-all duration-300 fixed md:relative bottom-0 z-50">
    <div class="p-4 border-b border-white/10 flex items-center justify-between">
      <div class="font-semibold text-xl logo-text">Efsane Baharat</div>
      <button id="toggleNav" class="w-8 h-8 flex items-center justify-center hover:bg-white/10 rounded">
        <i class="ri-menu-fold-line text-xl"></i>
      </button>
    </div>
    
    <!-- Mobil Menü -->
    <div class="mobile-nav md:hidden overflow-x-auto scrollbar-thin">
      <?php if (sayfaErisimKontrol($pdo, 'index.php')): ?>
      <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
        <i class="ri-dashboard-line"></i>
        <span>Ana Sayfa</span>
      </a>
      <?php endif; ?>
      
      <?php if (sayfaErisimKontrol($pdo, 'satis.php')): ?>
      <a href="satis.php" class="<?= basename($_SERVER['PHP_SELF']) == 'satis.php' ? 'active' : '' ?>">
        <i class="ri-shopping-cart-line"></i>
        <span>Satış</span>
      </a>
      <?php endif; ?>
      
      <?php if (sayfaErisimKontrol($pdo, 'urunler.php')): ?>
      <a href="urunler.php" class="<?= basename($_SERVER['PHP_SELF']) == 'urunler.php' ? 'active' : '' ?>">
        <i class="ri-store-2-line"></i>
        <span>Ürünler</span>
      </a>
      <?php endif; ?>
      
      <?php if (sayfaErisimKontrol($pdo, 'musteriler.php')): ?>
      <a href="musteriler.php" class="<?= basename($_SERVER['PHP_SELF']) == 'musteriler.php' ? 'active' : '' ?>">
        <i class="ri-user-3-line"></i>
        <span>Müşteriler</span>
      </a>
      <?php endif; ?>
      
      <?php if (sayfaErisimKontrol($pdo, 'alis.php')): ?>
      <a href="alis.php" class="<?= basename($_SERVER['PHP_SELF']) == 'alis.php' ? 'active' : '' ?>">
        <i class="ri-shopping-basket-line"></i>
        <span>Alış</span>
      </a>
      <?php endif; ?>
      
      <?php if (sayfaErisimKontrol($pdo, 'tahsilat.php')): ?>
      <a href="tahsilat.php" class="<?= basename($_SERVER['PHP_SELF']) == 'tahsilat.php' ? 'active' : '' ?>">
        <i class="ri-money-dollar-circle-line"></i>
        <span>Tahsilat</span>
      </a>
      <?php endif; ?>
      
      <?php if (sayfaErisimKontrol($pdo, 'ayarlar.php')): ?>
      <a href="ayarlar.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ayarlar.php' ? 'active' : '' ?>">
        <i class="ri-settings-line"></i>
        <span>Ayarlar</span>
      </a>
      <?php endif; ?>
    </div>
    
    <div class="flex-1 overflow-y-auto">
      <div class="p-2">
        <!-- Menü Linkleri -->
        <?php if (sayfaErisimKontrol($pdo, 'index.php')): ?>
        <a href="index.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Dashboard">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-dashboard-line"></i>
          </div>
          <span class="nav-text">Dashboard</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'satis.php')): ?>
        <a href="satis.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Satış">
          <i class="ri-shopping-cart-line text-xl"></i>
          <span class="nav-text">Satış</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'alis.php')): ?>
        <a href="alis.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Alış">
          <i class="ri-shopping-basket-line text-xl"></i>
          <span class="nav-text">Alış</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'alis_faturalari.php')): ?>
        <a href="alis_faturalari.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Alış Faturaları">
          <i class="ri-file-list-line text-xl"></i>
          <span class="nav-text">Alış Faturaları</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'tahsilat.php')): ?>
        <a href="tahsilat.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Tahsilat">
          <i class="ri-money-dollar-circle-line text-xl"></i>
          <span class="nav-text">Tahsilat</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'urunler.php')): ?>
        <a href="urunler.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Ürünler">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-store-2-line"></i>
          </div>
          <span class="nav-text">Ürünler</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'musteriler.php')): ?>
        <a href="musteriler.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Müşteriler">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-user-3-line"></i>
          </div>
          <span class="nav-text">Müşteriler</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'onay_merkezi.php')): ?>
        <a href="onay_merkezi.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Onay Merkezi">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-check-double-line"></i>
          </div>
          <span class="nav-text">Onay Merkezi</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'kullanicilar.php')): ?>
        <a href="kullanicilar.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Kullanıcılar">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-user-settings-line"></i>
          </div>
          <span class="nav-text">Kullanıcılar</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'roller.php')): ?>
        <a href="roller.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Roller">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-shield-user-line"></i>
          </div>
          <span class="nav-text">Roller</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'raporlar.php')): ?>
        <a href="#" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Raporlar">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-file-chart-line"></i>
          </div>
          <span class="nav-text">Raporlar</span>
        </a>
        <?php endif; ?>
        
        <?php if (sayfaErisimKontrol($pdo, 'ayarlar.php')): ?>
        <a href="ayarlar.php" class="tooltip flex items-center space-x-3 p-3 rounded hover:bg-white/10 mb-1" data-tooltip="Ayarlar">
          <div class="w-5 h-5 flex items-center justify-center">
            <i class="ri-settings-line"></i>
          </div>
          <span class="nav-text">Ayarlar</span>
        </a>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Profil Kısmı - Sol Menünün Alt Kısmında -->
    <div class="border-t border-white/10 p-3 hidden md:block">
      <div class="relative">
        <button id="userMenuBtn" class="tooltip w-full flex items-center space-x-3 p-2 rounded hover:bg-white/10 text-sm" data-tooltip="Profil">
          <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
            <i class="ri-user-line"></i>
          </div>
          <div class="nav-text text-left flex-1">
            <div class="font-medium truncate"><?php echo isset($_SESSION['kullanici_adi']) ? htmlspecialchars($_SESSION['kullanici_adi']) : 'Kullanıcı'; ?></div>
          </div>
          <i class="ri-arrow-down-s-line nav-text"></i>
        </button>
        <div id="userMenu" class="absolute left-0 bottom-full mb-2 w-full bg-white text-gray-800 rounded-md shadow-lg py-1 z-50 hidden">
          <a href="ayarlar.php" class="block px-4 py-2 text-sm hover:bg-gray-100">
            <i class="ri-settings-line mr-2"></i> Ayarlar
          </a>
          <a href="logout.php" class="block px-4 py-2 text-sm hover:bg-gray-100">
            <i class="ri-logout-box-line mr-2"></i> Çıkış Yap
          </a>
        </div>
      </div>
    </div>
  </nav>

  <!-- Ana İçerik -->
  <main class="flex-1 flex flex-col overflow-hidden">
    <div class="flex-1 overflow-auto p-0 pt-0 md:pt-4">
      <?php if (isset($showBackButton) && $showBackButton === true): ?>
      <div class="px-4 mb-4">
        <button
          onclick="window.location.href='<?= $backUrl ?? 'javascript:history.back()' ?>'"
          class="px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-button text-sm flex items-center"
        >
          <i class="ri-arrow-left-line mr-2"></i> Geri
        </button>
      </div>
      <?php endif; ?>
      <!-- Sayfa içeriği burada başlayacak -->

<!-- JavaScript: User Menu Toggle -->
<script>
  // User dropdown menu
  const userMenuBtn = document.getElementById('userMenuBtn');
  const userMenu = document.getElementById('userMenu');
  const toggleNav = document.getElementById('toggleNav');
  const sideNav = document.getElementById('sideNav');

  if (userMenuBtn && userMenu) {
    userMenuBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      userMenu.classList.toggle('hidden');
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
      if (!userMenuBtn.contains(e.target) && !userMenu.contains(e.target)) {
        userMenu.classList.add('hidden');
      }
    });
  }
  
  // Toggle sidebar
  if (toggleNav && sideNav) {
    toggleNav.addEventListener('click', () => {
      sideNav.classList.toggle('collapsed');
      
      // Icon değiştir
      const icon = toggleNav.querySelector('i');
      if (sideNav.classList.contains('collapsed')) {
        icon.classList.remove('ri-menu-fold-line');
        icon.classList.add('ri-menu-unfold-line');
      } else {
        icon.classList.remove('ri-menu-unfold-line');
        icon.classList.add('ri-menu-fold-line');
      }
    });
  }
</script>

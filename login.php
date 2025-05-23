<?php
// login.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Session varsa temizleyelim (diğer oturum temizlemeleri için)
session_regenerate_id(true);

// Eğer kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (girisYapmisMi()) {
    header('Location: index.php');
    exit();
}

// Hatırlama token'ı kontrolü
if (!girisYapmisMi() && hatirlamaTokeniKontrol($pdo)) {
    // Token doğrulandı, ana sayfaya yönlendir
    header('Location: index.php');
    exit();
}

$hata_mesaji = '';
$basari_mesaji = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eposta = trim($_POST['eposta'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $beni_hatirla = isset($_POST['beni_hatirla']);
    
    // Basit form doğrulama
    if (empty($eposta) || empty($sifre)) {
        $hata_mesaji = 'E-posta ve şifre alanları zorunludur.';
    } else {
        // Oturumu temizleyelim
        $_SESSION = array();
        
        // Eğer çerez varsa temizle
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Oturumu yenile
        session_regenerate_id(true);
        
        // Giriş işlemini dene
        $sonuc = kullaniciGiris($pdo, $eposta, $sifre, $beni_hatirla);
        
        if ($sonuc) {
            // Başarılı giriş, ana sayfaya yönlendir
            header('Location: index.php');
            exit();
        } else {
            // Başarısız giriş
            $hata_mesaji = 'Geçersiz e-posta veya şifre.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <title>Giriş Yap - Efsane Baharat</title>
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
</head>
<body class="bg-gray-50">
  <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8 bg-white p-8 rounded-lg shadow-sm">
      <div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Efsane Baharat
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Yönetim Paneline Giriş Yapın
        </p>
      </div>
      
      <?php if ($hata_mesaji): ?>
      <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
        <p><?= htmlspecialchars($hata_mesaji) ?></p>
      </div>
      <?php endif; ?>
      
      <?php if ($basari_mesaji): ?>
      <div class="bg-green-50 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
        <p><?= htmlspecialchars($basari_mesaji) ?></p>
      </div>
      <?php endif; ?>
      
      <form class="mt-8 space-y-6" method="POST" action="login.php">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="eposta" class="sr-only">E-posta Adresi</label>
            <input id="eposta" name="eposta" type="email" autocomplete="email" required 
              class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm" 
              placeholder="E-posta adresi"
              value="<?= htmlspecialchars($_POST['eposta'] ?? '') ?>">
          </div>
          <div>
            <label for="sifre" class="sr-only">Şifre</label>
            <input id="sifre" name="sifre" type="password" autocomplete="current-password" required 
              class="appearance-none rounded-none relative block w-full px-3 py-3 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-primary focus:border-primary focus:z-10 sm:text-sm" 
              placeholder="Şifre">
          </div>
        </div>

        <div class="flex items-center justify-between">
          <div class="flex items-center">
            <input id="beni_hatirla" name="beni_hatirla" type="checkbox" 
              class="h-4 w-4 text-primary focus:ring-primary border-gray-300 rounded">
            <label for="beni_hatirla" class="ml-2 block text-sm text-gray-900">
              Beni hatırla
            </label>
          </div>

          <div class="text-sm">
            <a href="#" class="font-medium text-primary hover:text-primary/80">
              Şifremi unuttum
            </a>
          </div>
        </div>

        <div>
          <button type="submit" 
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
              <i class="ri-login-circle-line"></i>
            </span>
            Giriş Yap
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <!-- TailwindCSS Primary Renk Tanımlaması -->
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            primary: {
              DEFAULT: '#4f46e5',
              '50': '#eef2ff',
              '100': '#e0e7ff',
              '200': '#c7d2fe',
              '300': '#a5b4fc',
              '400': '#818cf8',
              '500': '#6366f1',
              '600': '#4f46e5',
              '700': '#4338ca',
              '800': '#3730a3',
              '900': '#312e81',
              '950': '#1e1b4b',
            }
          }
        }
      }
    }
  </script>
</body>
</html> 
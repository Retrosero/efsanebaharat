<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['kullanici_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eposta = trim($_POST['eposta'] ?? '');
    $sifre = $_POST['sifre'] ?? '';

    if (empty($eposta) || empty($sifre)) {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE eposta = :eposta AND aktif = 1");
            $stmt->execute([':eposta' => $eposta]);
            $kullanici = $stmt->fetch();

            if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
                // Oturum bilgilerini kaydet
                $_SESSION['kullanici_id'] = $kullanici['id'];
                $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
                $_SESSION['eposta'] = $kullanici['eposta'];
                $_SESSION['rol_id'] = $kullanici['rol_id'];

                // Ana sayfaya yönlendir
                header('Location: index.php');
                exit;
            } else {
                $error = 'Geçersiz e-posta veya şifre.';
            }
        } catch (PDOException $e) {
            $error = 'Giriş yapılırken bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Efsane Baharat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Pacifico&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.5.0/fonts/remixicon.css" rel="stylesheet">
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
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full mx-auto p-6">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-pacifico text-primary mb-2">Efsane Baharat</h1>
            <p class="text-gray-600">Yönetim Paneli</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm p-6 border">
            <h2 class="text-2xl font-medium mb-6">Giriş Yap</h2>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">E-posta</label>
                    <input type="email" name="eposta" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Şifre</label>
                    <input type="password" name="sifre" class="w-full px-3 py-2 border border-gray-300 rounded-button focus:outline-none focus:ring-2 focus:ring-primary/20" required>
                </div>

                <button type="submit" class="w-full bg-primary text-white py-2 px-4 rounded-button hover:bg-primary/90">
                    Giriş Yap
                </button>
            </form>
        </div>
    </div>
</body>
</html> 
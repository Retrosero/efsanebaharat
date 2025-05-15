<?php
// Veritabanı bağlantısını dahil et
require_once 'includes/db.php';

// Kullanıcı bilgileri
$kullanici_adi = 'Yönetici';
$eposta = 'info@efsanebaharat.com';
$sifre = 'admin123456';
$rol_id = 1; // Yönetici rolü

// Şifreyi hashle
$hashed_password = password_hash($sifre, PASSWORD_DEFAULT);

try {
    // Önce bu e-posta ile kullanıcı var mı kontrol et
    $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar WHERE eposta = :eposta");
    $check_stmt->execute([':eposta' => $eposta]);
    $user_exists = $check_stmt->fetchColumn();

    if ($user_exists) {
        echo "<div style='background-color: #ffcccc; padding: 15px; border-radius: 5px; margin: 20px;'>";
        echo "<h3>Hata!</h3>";
        echo "<p>Bu e-posta adresi ({$eposta}) zaten kullanılıyor. Lütfen başka bir e-posta adresi deneyin.</p>";
        echo "</div>";
    } else {
        // Kullanıcıyı ekle
        $stmt = $pdo->prepare("
            INSERT INTO kullanicilar (kullanici_adi, eposta, sifre, rol_id, aktif)
            VALUES (:kullanici_adi, :eposta, :sifre, :rol_id, 1)
        ");
        
        $stmt->execute([
            ':kullanici_adi' => $kullanici_adi,
            ':eposta' => $eposta,
            ':sifre' => $hashed_password,
            ':rol_id' => $rol_id
        ]);
        
        echo "<div style='background-color: #ccffcc; padding: 15px; border-radius: 5px; margin: 20px;'>";
        echo "<h3>Başarılı!</h3>";
        echo "<p>Yeni kullanıcı başarıyla oluşturuldu.</p>";
        echo "<p><strong>E-posta:</strong> {$eposta}</p>";
        echo "<p><strong>Şifre:</strong> {$sifre}</p>";
        echo "<p><strong>Rol:</strong> Yönetici</p>";
        echo "<p>Bu bilgileri güvenli bir yerde saklayın.</p>";
        echo "<p><a href='login.php' style='background-color: #4CAF50; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px;'>Giriş Yap</a></p>";
        echo "</div>";
    }
} catch (PDOException $e) {
    echo "<div style='background-color: #ffcccc; padding: 15px; border-radius: 5px; margin: 20px;'>";
    echo "<h3>Hata!</h3>";
    echo "<p>Kullanıcı oluşturulurken bir hata oluştu: " . $e->getMessage() . "</p>";
    echo "</div>";
}

// Bu dosyayı kullandıktan sonra güvenlik için silmeniz önerilir
echo "<div style='background-color: #ffffcc; padding: 15px; border-radius: 5px; margin: 20px;'>";
echo "<h3>Güvenlik Uyarısı</h3>";
echo "<p>Bu dosyayı kullandıktan sonra sunucunuzdan silmeniz önerilir.</p>";
echo "</div>";
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Kullanıcı Oluştur</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Yeni Kullanıcı Oluştur</h1>
        <p>Bu sayfa, veritabanına yeni bir yönetici kullanıcısı eklemek için kullanılır.</p>
    </div>
</body>
</html> 
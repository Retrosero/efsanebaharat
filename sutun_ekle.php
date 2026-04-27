<?php
// Veritabanı bağlantısı
$host = 'localhost';
$dbname = 'efsaneba_uygulama';
$user = 'efsaneba_serhan';
$pass = 'Aooh8x!!!4189';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Faturalar tablosuna para_birimi sütunu ekle (yoksa)
    try {
        $pdo->exec("ALTER TABLE faturalar ADD COLUMN para_birimi VARCHAR(10) DEFAULT 'TRY' AFTER kalan_tutar");
        echo "para_birimi sütunu eklendi<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "para_birimi sütunu zaten mevcut<br>";
        } else {
            echo "para_birimi hata: " . $e->getMessage() . "<br>";
        }
    }
    
    // Faturalar tablosuna onay_durumu sütunu ekle (yoksa)
    try {
        $pdo->exec("ALTER TABLE faturalar ADD COLUMN onay_durumu VARCHAR(20) DEFAULT 'bekliyor' AFTER para_birimi");
        echo "onay_durumu sütunu eklendi<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "onay_durumu sütunu zaten mevcut<br>";
        } else {
            echo "onay_durumu hata: " . $e->getMessage() . "<br>";
        }
    }
    
    // Odeme_tahsilat tablosuna onay_durumu sütunu ekle (yoksa)
    try {
        $pdo->exec("ALTER TABLE odeme_tahsilat ADD COLUMN onay_durumu VARCHAR(20) DEFAULT 'bekliyor'");
        echo "odeme_tahsilat onay_durumu sütunu eklendi<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "odeme_tahsilat onay_durumu sütunu zaten mevcut<br>";
        } else {
            echo "odeme_tahsilat onay_durumu hata: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<br>Tüm sütunlar başarıyla eklendi veya zaten mevcut!";
    
} catch (PDOException $e) {
    echo "Veritabanı bağlantı hatası: " . $e->getMessage();
}
?>
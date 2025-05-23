<?php
// kullanicilar tablosuna gerekli token sütunlarını eklemek için script

require_once 'includes/db.php';

try {
    // Sütunların var olup olmadığını kontrol etmek için bilgi şemasını sorgulayalım
    $columns = $pdo->query("SHOW COLUMNS FROM kullanicilar")->fetchAll(PDO::FETCH_COLUMN);
    
    // remember_token sütunu yoksa ekleyelim
    if (!in_array('remember_token', $columns)) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL");
        echo "remember_token sütunu eklendi.<br>";
    } else {
        echo "remember_token sütunu zaten var.<br>";
    }
    
    // token_expires_at sütunu yoksa ekleyelim
    if (!in_array('token_expires_at', $columns)) {
        $pdo->exec("ALTER TABLE kullanicilar ADD COLUMN token_expires_at DATETIME DEFAULT NULL");
        echo "token_expires_at sütunu eklendi.<br>";
    } else {
        echo "token_expires_at sütunu zaten var.<br>";
    }
    
    echo "İşlem tamamlandı. Bu dosyayı güvenlik nedeniyle silmeniz önerilir.";
    
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
} 
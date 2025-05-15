<?php
// musteri_guncelle.php

require_once 'includes/db.php';

// POST verisi kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $musteri_id = isset($_POST['musteri_id']) ? intval($_POST['musteri_id']) : 0;
    
    if (!$musteri_id) {
        die("Geçersiz müşteri ID!");
    }
    
    // Form verilerini al
    $musteri_kodu = trim($_POST['musteri_kodu'] ?? '');
    $tip_id = !empty($_POST['tip_id']) ? intval($_POST['tip_id']) : null;
    $ad = trim($_POST['ad'] ?? '');
    $soyad = trim($_POST['soyad'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $vergi_no = trim($_POST['vergi_no'] ?? '');
    $vergi_dairesi = trim($_POST['vergi_dairesi'] ?? '');
    $adres = trim($_POST['adres'] ?? '');
    $notlar = trim($_POST['notlar'] ?? '');
    // Aktif/Pasif durumunu al
    $aktif = isset($_POST['aktif']) ? (int)$_POST['aktif'] : 1;
    
    // Validasyon
    if (empty($ad)) {
        $updateError = "Müşteri adı boş olamaz.";
        header("Location: musteri_detay.php?id=$musteri_id&error=" . urlencode($updateError));
        exit;
    }
    
    if (empty($musteri_kodu)) {
        $updateError = "Müşteri kodu boş olamaz.";
        header("Location: musteri_detay.php?id=$musteri_id&error=" . urlencode($updateError));
        exit;
    }
    
    // Müşteri kodunun benzersiz olup olmadığını kontrol et (kendi ID'si hariç)
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) FROM musteriler 
        WHERE musteri_kodu = :code AND id != :id
    ");
    $stmtCheck->execute([
        ':code' => $musteri_kodu,
        ':id' => $musteri_id
    ]);
    
    if ($stmtCheck->fetchColumn() > 0) {
        $updateError = "Bu müşteri kodu zaten başka bir müşteri tarafından kullanılıyor.";
        header("Location: musteri_detay.php?id=$musteri_id&error=" . urlencode($updateError));
        exit;
    }
    
    try {
        // Müşteri bilgilerini güncelle
        $stmt = $pdo->prepare("
            UPDATE musteriler SET
                musteri_kodu = :musteri_kodu,
                tip_id = :tip_id,
                ad = :ad,
                soyad = :soyad,
                telefon = :telefon,
                email = :email,
                vergi_no = :vergi_no,
                vergi_dairesi = :vergi_dairesi,
                adres = :adres,
                notlar = :notlar,
                aktif = :aktif,
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':musteri_kodu' => $musteri_kodu,
            ':tip_id' => $tip_id,
            ':ad' => $ad,
            ':soyad' => $soyad,
            ':telefon' => $telefon,
            ':email' => $email,
            ':vergi_no' => $vergi_no,
            ':vergi_dairesi' => $vergi_dairesi,
            ':adres' => $adres,
            ':notlar' => $notlar,
            ':aktif' => $aktif,
            ':id' => $musteri_id
        ]);
        
        // Başarılı güncelleme
        header("Location: musteri_detay.php?id=$musteri_id&success=1");
        exit;
        
    } catch (PDOException $e) {
        // UNIQUE kısıtlaması hatası için özel mesaj
        if ($e->getCode() == 23000 && strpos($e->getMessage(), 'idx_musteri_kodu')) {
            $updateError = "Bu müşteri kodu zaten başka bir müşteri tarafından kullanılıyor.";
        } else {
            $updateError = "Güncelleme sırasında bir hata oluştu: " . $e->getMessage();
        }
        header("Location: musteri_detay.php?id=$musteri_id&error=" . urlencode($updateError));
        exit;
    }
} else {
    // POST değilse ana sayfaya yönlendir
    header("Location: musteriler.php");
    exit;
} 
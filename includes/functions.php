<?php
// Yetki kontrolü fonksiyonu
function yetkiKontrol($sayfa, $yetki_turu = 'goruntuleme') {
    global $pdo;
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['rol_id'])) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT sy.* 
            FROM sayfa_yetkileri sy
            WHERE sy.rol_id = ? AND sy.sayfa_adi = ?
        ");
        $stmt->execute([$_SESSION['rol_id'], $sayfa]);
        $yetki = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$yetki) {
            return false;
        }
        
        return $yetki[$yetki_turu] == 1;
        
    } catch(PDOException $e) {
        error_log("Yetki kontrolü hatası: " . $e->getMessage());
        return false;
    }
}

// Para formatı fonksiyonu
function paraFormat($tutar) {
    return number_format($tutar, 2, ',', '.');
}

// Güvenli metin fonksiyonu
function guvenliMetin($metin) {
    return htmlspecialchars(trim($metin), ENT_QUOTES, 'UTF-8');
}

// Tarih formatı fonksiyonu
function tarihFormat($tarih, $format = 'd.m.Y') {
    return date($format, strtotime($tarih));
}

// Dosya yükleme fonksiyonu
function dosyaYukle($dosya, $hedef_klasor, $izin_verilen_tipler = ['jpg', 'jpeg', 'png']) {
    if (!isset($dosya['error']) || $dosya['error'] != 0) {
        return false;
    }
    
    $dosya_tipi = strtolower(pathinfo($dosya['name'], PATHINFO_EXTENSION));
    if (!in_array($dosya_tipi, $izin_verilen_tipler)) {
        return false;
    }
    
    $yeni_isim = uniqid() . '.' . $dosya_tipi;
    $hedef_yol = $hedef_klasor . '/' . $yeni_isim;
    
    if (move_uploaded_file($dosya['tmp_name'], $hedef_yol)) {
        return $yeni_isim;
    }
    
    return false;
}

// Bildirim fonksiyonu
function bildirimEkle($kullanici_id, $mesaj, $tur = 'info') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO bildirimler (kullanici_id, mesaj, tur, okundu)
            VALUES (?, ?, ?, 0)
        ");
        return $stmt->execute([$kullanici_id, $mesaj, $tur]);
    } catch(PDOException $e) {
        error_log("Bildirim ekleme hatası: " . $e->getMessage());
        return false;
    }
}

// Log kayıt fonksiyonu
function logKaydet($islem, $aciklama, $kullanici_id = null) {
    global $pdo;
    
    if ($kullanici_id === null && isset($_SESSION['user_id'])) {
        $kullanici_id = $_SESSION['user_id'];
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO sistem_log (kullanici_id, islem, aciklama, ip_adresi)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $kullanici_id,
            $islem,
            $aciklama,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch(PDOException $e) {
        error_log("Log kayıt hatası: " . $e->getMessage());
        return false;
    }
} 
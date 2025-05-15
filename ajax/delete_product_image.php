<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

try {
    // JSON verisini al
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['urun_id']) || !isset($data['resim_index'])) {
        throw new Exception('Gerekli parametreler eksik');
    }

    $urun_id = intval($data['urun_id']);
    $resim_index = intval($data['resim_index']);

    // Ürünü ve resmi kontrol et
    if ($resim_index === 0) {
        $stmt = $pdo->prepare("SELECT resim_url FROM urunler WHERE id = :id");
        $resim_kolonu = 'resim_url';
    } else {
        $stmt = $pdo->prepare("SELECT resim_url_" . $resim_index . " FROM urunler WHERE id = :id");
        $resim_kolonu = 'resim_url_' . $resim_index;
    }
    
    $stmt->execute([':id' => $urun_id]);
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$urun) {
        throw new Exception('Ürün bulunamadı');
    }

    $eski_resim = $urun[$resim_kolonu];

    // Eski resmi fiziksel olarak sil
    if (!empty($eski_resim) && file_exists($eski_resim)) {
        @unlink($eski_resim);
    }

    // Veritabanından resim referansını sil
    if ($resim_index === 0) {
        $stmt = $pdo->prepare("UPDATE urunler SET resim_url = NULL WHERE id = :id");
    } else {
        $stmt = $pdo->prepare("UPDATE urunler SET resim_url_" . $resim_index . " = NULL WHERE id = :id");
    }
    $stmt->execute([':id' => $urun_id]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 
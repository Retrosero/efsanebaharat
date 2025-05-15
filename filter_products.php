<?php
require_once 'includes/db.php';

// JSON verisini al
$data = json_decode(file_get_contents('php://input'), true);

// Filtreleri al
$minPrice = $data['minPrice'] ? floatval($data['minPrice']) : null;
$maxPrice = $data['maxPrice'] ? floatval($data['maxPrice']) : null;
$brand = $data['brand'] ?? '';
$package = $data['package'] ?? '';
$minStock = $data['minStock'] ? intval($data['minStock']) : null;
$maxStock = $data['maxStock'] ? intval($data['maxStock']) : null;

// SQL sorgusu oluştur
$sql = "
    SELECT u.*, m.marka_adi 
    FROM urunler u
    LEFT JOIN markalar m ON u.marka_id = m.id 
    WHERE 1=1
";
$params = [];

// Fiyat filtresi
if ($minPrice !== null) {
    $sql .= " AND birim_fiyat >= :minPrice";
    $params[':minPrice'] = $minPrice;
}
if ($maxPrice !== null) {
    $sql .= " AND birim_fiyat <= :maxPrice";
    $params[':maxPrice'] = $maxPrice;
}

// Stok filtresi
if ($minStock !== null) {
    $sql .= " AND stok_miktari >= :minStock";
    $params[':minStock'] = $minStock;
}
if ($maxStock !== null) {
    $sql .= " AND stok_miktari <= :maxStock";
    $params[':maxStock'] = $maxStock;
}

// Sorguyu çalıştır
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonuçları formatla
    $formattedProducts = array_map(function($p) {
        return [
            'id' => $p['id'],
            'name' => $p['urun_adi'],
            'price' => number_format($p['birim_fiyat'], 2, ',', '.'),
            'stock' => $p['stok_miktari'],
            'brand' => $p['marka_adi'] ?? 'Belirtilmemiş', // Marka adı eklendi
            'package' => 'Belirtilmemiş', // Geçici olarak sabit değer
            'image' => $p['resim_url'] ?? null
        ];
    }, $products);
    
    // JSON olarak döndür
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => $formattedProducts
    ]);
    
} catch(Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
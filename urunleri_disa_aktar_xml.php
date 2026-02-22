<?php
// urunleri_disa_aktar_xml.php
// Bu dosya ürünleri XML formatında dışarı aktarmak için oluşturulmuştur.

require_once 'config.php';

// Hata ayıklama çıktısını kapatıp sadece XML basmak için
error_reporting(0);
ini_set('display_errors', 0);

// XML içeriğini bir değişkende toplayalım
$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xmlContent .= "<Products>\n";

try {
    $sql = "SELECT 
                u.id, 
                u.urun_kodu, 
                u.barkod, 
                u.urun_adi, 
                u.kategori_id, 
                k.kategori_adi,
                u.alt_kategori_id,
                ak.alt_kategori_adi,
                u.birim_fiyat,
                u.kdv_orani,
                u.stok_miktari,
                u.marka_id,
                m.marka_adi,
                u.resim_url,
                u.resim_url_1,
                u.resim_url_2,
                u.resim_url_3,
                u.resim_url_4,
                u.resim_url_5,
                u.aciklama
            FROM urunler u
            LEFT JOIN kategoriler k ON u.kategori_id = k.id
            LEFT JOIN alt_kategoriler ak ON u.alt_kategori_id = ak.id
            LEFT JOIN markalar m ON u.marka_id = m.id
            WHERE u.aktif = 1";

    $stmt = $db->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $xmlContent .= "<Product>\n";
        
        $xmlContent .= "<Product_code>" . htmlspecialchars($row['urun_kodu'] ?? '') . "</Product_code>\n";
        $xmlContent .= "<Product_id>" . htmlspecialchars($row['id'] ?? '') . "</Product_id>\n";
        $xmlContent .= "<Barcode>" . htmlspecialchars($row['barkod'] ?? '') . "</Barcode>\n";
        $xmlContent .= "<Name>" . htmlspecialchars($row['urun_adi'] ?? '') . "</Name>\n";
        
        $xmlContent .= "<mainCategory>" . htmlspecialchars($row['kategori_adi'] ?? '') . "</mainCategory>\n";
        $xmlContent .= "<mainCategory_id>" . htmlspecialchars($row['kategori_id'] ?? '') . "</mainCategory_id>\n";
        
        $xmlContent .= "<category>" . htmlspecialchars($row['alt_kategori_adi'] ?? '') . "</category>\n";
        $xmlContent .= "<category_id>" . htmlspecialchars($row['alt_kategori_id'] ?? '') . "</category_id>\n";
        
        $xmlContent .= "<subCategory></subCategory>\n";
        $xmlContent .= "<subCategory_id></subCategory_id>\n";
        
        $xmlContent .= "<desi>0</desi>\n";
        
        // Fiyat formatlaması
        $fiyat = isset($row['birim_fiyat']) ? number_format((float)$row['birim_fiyat'], 2, '.', '') : '0.00';
        $xmlContent .= "<Price>" . $fiyat . "</Price>\n";
        
        $xmlContent .= "<CurrencyType>TRL</CurrencyType>\n";
        
        $vergi = isset($row['kdv_orani']) ? (int)$row['kdv_orani'] : 20;
        $xmlContent .= "<Tax>" . $vergi . "</Tax>\n";
        
        $stok = isset($row['stok_miktari']) ? (int)$row['stok_miktari'] : 0;
        $xmlContent .= "<Stock>" . $stok . "</Stock>\n";
        
        $xmlContent .= "<Brand>" . htmlspecialchars($row['marka_adi'] ?? '') . "</Brand>\n";
        
        // Resimlerin listesini array haline getirelim (resim_url'yi ilk başa koyalım)
        $resimler = [];
        if (!empty($row['resim_url'])) $resimler[] = $row['resim_url'];
        if (!empty($row['resim_url_1'])) $resimler[] = $row['resim_url_1'];
        if (!empty($row['resim_url_2'])) $resimler[] = $row['resim_url_2'];
        if (!empty($row['resim_url_3'])) $resimler[] = $row['resim_url_3'];
        if (!empty($row['resim_url_4'])) $resimler[] = $row['resim_url_4'];
        if (!empty($row['resim_url_5'])) $resimler[] = $row['resim_url_5'];

        // Resimler (1'den 5'e kadar XML taglari bastiralim)
        for ($i = 0; $i < 5; $i++) {
            $tagIndex = $i + 1;
            if (isset($resimler[$i])) {
                $img = $resimler[$i];
                
                // Url eger http ile baslamiyorsa istenildigi gibi 'http://panel.efsanebaharat.com/' ekleyelim
                if (strpos($img, 'http') !== 0) {
                    // Yolların başına slash'i düzgün koyduğumuzdan emin olalım ve boşlukları '%20'ye çevirelim
                    if ($img[0] === '/') {
                        $img = substr($img, 1);
                    }
                    $img = 'http://panel.efsanebaharat.com/' . $img;
                }
                
                // Url içindeki boşlukları (space) %20 olarak değiştir
                $img = str_replace(' ', '%20', $img);
                
                $xmlContent .= "<Image$tagIndex>" . htmlspecialchars($img) . "</Image$tagIndex>\n";
            } else {
                $xmlContent .= "<Image$tagIndex/>\n";
            }
        }
        
        // Aciklama (Eski CDATA burada kaldırıldı, onun yerine XML uygunluğunda htmlspecialchars edildi)
        $aciklama = $row['aciklama'] ?? '';
        $xmlContent .= "<Description>" . htmlspecialchars($aciklama) . "</Description>\n";
        
        $xmlContent .= "</Product>\n";
    }

} catch (PDOException $e) {
    // Hata durumunda XML formatında minimal hata bilgisi görelim
    $xmlContent .= "<!-- Veritabani Hatasi: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

$xmlContent .= "</Products>\n";

// 1. Oluşturulan XML'i sunucuda "urunler.xml" adıyla ana dizine kaydet.
$dosyaYolu = __DIR__ . '/urunler.xml';
file_put_contents($dosyaYolu, $xmlContent);

// 2. Kullanıcının indirebilmesi için başlıkları (headers) gönder
header('Content-Type: text/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="urunler.xml"');

// 3. XML'i ekrana (indirilen dosyaya) bas
echo $xmlContent;

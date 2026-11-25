<?php
// satis_kaydet.php
header('Content-Type: application/json; charset=utf-8');
require_once 'includes/db.php';   // oturum + giriş kontrolü + CSRF (Origin) kontrolü
require_once 'includes/birim.php';

class StokYetersizException extends RuntimeException {}

function satisYanit($success, $message, array $extra = [])
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    satisYanit(false, 'Geçersiz istek');
}

if (!isset($_SESSION['kullanici_id'])) {
    http_response_code(401);
    satisYanit(false, 'Oturum açmanız gerekiyor');
}

$postData = json_decode(file_get_contents('php://input'), true);
if (!is_array($postData)) {
    satisYanit(false, 'Geçersiz veri');
}

$musteri_id   = filter_var($postData['musteri_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$items        = $postData['items'] ?? [];
$discountRate = is_numeric($postData['discountRate'] ?? 0) ? (float)$postData['discountRate'] : 0;
$orderNote    = mb_substr(trim((string)($postData['note'] ?? '')), 0, 1000);
$saleDate     = (string)($postData['saleDate'] ?? '');

$tarih = DateTime::createFromFormat('!Y-m-d', $saleDate);
if (!$tarih || $tarih->format('Y-m-d') !== $saleDate) {
    $saleDate = date('Y-m-d');
}
$discountRate = max(0, min(100, $discountRate));

if (!$musteri_id || !is_array($items) || empty($items)) {
    satisYanit(false, 'Müşteri veya ürün listesi yok');
}

try {
    $stmtMusteriVar = $pdo->prepare("SELECT id FROM musteriler WHERE id = ?");
    $stmtMusteriVar->execute([$musteri_id]);
    if (!$stmtMusteriVar->fetchColumn()) {
        satisYanit(false, 'Müşteri bulunamadı');
    }

    // 1) Kalemleri doğrula, ürün birimine normalize et, stokları ürün bazında topla
    $stmtUrun = $pdo->prepare("SELECT id, stok_miktari, urun_adi, olcum_birimi, kdv_orani FROM urunler WHERE id = ?");
    $kalemler = [];
    $istenenStok = [];
    $urunler = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            satisYanit(false, 'Geçersiz ürün verisi');
        }
        $urun_id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$urun_id) {
            satisYanit(false, 'Geçersiz ürün');
        }
        if (!isset($urunler[$urun_id])) {
            $stmtUrun->execute([$urun_id]);
            $urunler[$urun_id] = $stmtUrun->fetch(PDO::FETCH_ASSOC);
        }
        $urun = $urunler[$urun_id];
        if (!$urun) {
            satisYanit(false, 'Ürün bulunamadı! Ürün ID: ' . $urun_id);
        }

        try {
            $hesap = satisKalemiHesapla($item, $urun['olcum_birimi']);
        } catch (InvalidArgumentException $e) {
            satisYanit(false, $urun['urun_adi'] . ': ' . $e->getMessage());
        }

        $istenenStok[$urun_id] = ($istenenStok[$urun_id] ?? 0) + $hesap['miktar'];
        $kalemler[] = $hesap + [
            'urun_id' => $urun_id,
            'not' => mb_substr((string)($item['note'] ?? ''), 0, 500),
        ];
    }

    foreach ($istenenStok as $urun_id => $miktar) {
        $urun = $urunler[$urun_id];
        // Kayan nokta toleransı
        if ((float)$urun['stok_miktari'] + 1e-9 < $miktar) {
            satisYanit(false, 'Yetersiz stok! Ürün: ' . $urun['urun_adi'] . ', Mevcut: ' . (float)$urun['stok_miktari'] . ' ' . $urun['olcum_birimi'] . ', İstenen: ' . round($miktar, 3) . ' ' . $urun['olcum_birimi']);
        }
    }

    // 2) Toplamlar (satış birimindeki miktar x fiyat)
    $araToplam = array_sum(array_column($kalemler, 'toplam'));
    $iskonto = $araToplam * ($discountRate / 100);
    $netToplam = $araToplam - $iskonto;

    $pdo->beginTransaction();

    // 3) Fatura
    $stmtF = $pdo->prepare("
        INSERT INTO faturalar(
            fatura_turu, musteri_id, toplam_tutar, odeme_durumu,
            fatura_tarihi, kullanici_id, indirim_tutari, genel_toplam, kalan_tutar, aciklama,
            created_at, onay_durumu, onayli
        ) VALUES(
            'satis', :mid, :toplam, 'odenmedi',
            :saleDate, :kullanici_id, :iskonto, :net_toplam, :net_toplam, :aciklama,
            NOW(), 'onaylandi', 1
        )
    ");
    $stmtF->execute([
        ':mid' => $musteri_id,
        ':toplam' => $araToplam,
        ':iskonto' => $iskonto,
        ':net_toplam' => $netToplam,
        ':aciklama' => $orderNote,
        ':kullanici_id' => $_SESSION['kullanici_id'],
        ':saleDate' => $saleDate,
    ]);
    $fatura_id = $pdo->lastInsertId();

    $stmtD = $pdo->prepare("
        INSERT INTO fatura_detaylari(
            fatura_id, urun_id, urun_adi, miktar, olcum_birimi,
            birim_fiyat, toplam_fiyat, kdv_orani, kdv_tutari,
            indirim_orani, indirim_tutari, net_tutar, urun_notu
        ) VALUES(
            :fid, :uid, :uadi, :qty, :olcum_birimi,
            :price, :toplam, :kdv_oran, :kdv_tutar,
            :indirim_orani, :indirim_tutari, :net, :note
        )
    ");
    // Stok, eşzamanlı satışlarda eksiye düşmesin diye koşullu düşülür
    $stmtStokGuncelle = $pdo->prepare("UPDATE urunler SET stok_miktari = stok_miktari - :qty WHERE id = :uid AND stok_miktari + 0.000001 >= :qty2");
    $stmtStokHareket = $pdo->prepare("
        INSERT INTO stok_hareketleri(urun_id, hareket_tipi, miktar, fatura_id, kullanici_id, aciklama, created_at)
        VALUES(:uid, 'cikis', :qty, :fid, :kullanici_id, :aciklama, NOW())
    ");

    foreach ($kalemler as $k) {
        $urun = $urunler[$k['urun_id']];
        $kdv_orani = (float)$urun['kdv_orani'];
        $toplam = $k['toplam'];
        $indirim_tutari = $toplam * ($discountRate / 100);
        $iskonto_sonrasi = $toplam - $indirim_tutari;
        $kdv_tutari = $iskonto_sonrasi * ($kdv_orani / 100);

        // Miktar ve birim fiyat ürünün kendi biriminde saklanır (miktar x birim_fiyat = toplam)
        $stmtD->execute([
            ':fid' => $fatura_id,
            ':uid' => $k['urun_id'],
            ':uadi' => $urun['urun_adi'],
            ':qty' => $k['miktar'],
            ':olcum_birimi' => $urun['olcum_birimi'],
            ':price' => $k['birim_fiyat'],
            ':toplam' => $toplam,
            ':kdv_oran' => $kdv_orani,
            ':kdv_tutar' => $kdv_tutari,
            ':indirim_orani' => $discountRate,
            ':indirim_tutari' => $indirim_tutari,
            ':net' => $iskonto_sonrasi + $kdv_tutari,
            ':note' => $k['not'],
        ]);

        $stmtStokGuncelle->execute([':qty' => $k['miktar'], ':qty2' => $k['miktar'], ':uid' => $k['urun_id']]);
        if ($stmtStokGuncelle->rowCount() !== 1) {
            throw new StokYetersizException('Yetersiz stok: ' . $urun['urun_adi']);
        }

        $stmtStokHareket->execute([
            ':uid' => $k['urun_id'],
            ':qty' => $k['miktar'],
            ':fid' => $fatura_id,
            ':kullanici_id' => $_SESSION['kullanici_id'],
            ':aciklama' => 'Satış faturası stok çıkışı',
        ]);
    }

    // 4) Müşteri cari bakiyesine borç
    $stmtMusteri = $pdo->prepare("UPDATE musteriler SET cari_bakiye = cari_bakiye + :tutar WHERE id = :musteri_id");
    $stmtMusteri->execute([':tutar' => $netToplam, ':musteri_id' => $musteri_id]);

    $pdo->commit();

    satisYanit(true, 'Satış başarıyla kaydedildi', [
        'fatura_id' => $fatura_id,
        'redirect' => 'onay_merkezi.php',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('satis_kaydet hata: ' . $e->getMessage());
    $mesaj = $e instanceof StokYetersizException ? $e->getMessage() : 'İşlem sırasında bir hata oluştu';
    satisYanit(false, $mesaj);
}

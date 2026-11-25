<?php
// includes/yedek.php
// Veritabanının sıkıştırılmış SQL dökümünü alır ve Cloudflare R2'deki (gizli) yedek kovasına yükler.

require_once __DIR__ . '/r2.php';

const YEDEK_ON_EK = 'veritabani/';

/**
 * Tüm tabloların yapı ve verisini, tetikleyicileri ve görünümleri gzip'li SQL dosyasına yazar.
 * Çıktı `mysql` istemcisiyle geri yüklenebilir.
 * @return array{tablo:int, satir:int}
 */
function veritabaniDokumuAl(PDO $pdo, string $hedefGz): array
{
    $gz = gzopen($hedefGz, 'wb6');
    if (!$gz) {
        throw new RuntimeException("Yedek dosyası oluşturulamadı: $hedefGz");
    }
    $yaz = function (string $metin) use ($gz) {
        gzwrite($gz, $metin);
    };

    $vt = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $yaz("-- Efsane Baharat veritabanı yedeği\n-- Veritabanı: `$vt`\n-- Tarih: " . date('Y-m-d H:i:s') . "\n\n");
    $yaz("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");

    $nesneler = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
    $tablolar = [];
    $gorunumler = [];
    foreach ($nesneler as [$ad, $tur]) {
        if ($tur === 'VIEW') {
            $gorunumler[] = $ad;
        } else {
            $tablolar[] = $ad;
        }
    }

    $toplamSatir = 0;
    foreach ($tablolar as $tablo) {
        $q = '`' . str_replace('`', '``', $tablo) . '`';
        $olustur = $pdo->query("SHOW CREATE TABLE $q")->fetch(PDO::FETCH_NUM)[1];
        $yaz("-- Tablo: $tablo\nDROP TABLE IF EXISTS $q;\n$olustur;\n\n");

        // İkili sütunlar hex olarak yazılır
        $ikili = [];
        foreach ($pdo->query("SHOW COLUMNS FROM $q")->fetchAll(PDO::FETCH_ASSOC) as $kolon) {
            $ikili[$kolon['Field']] = (bool)preg_match('/blob|binary/i', $kolon['Type']);
        }

        $stmt = $pdo->query("SELECT * FROM $q");
        $paket = [];
        $kolonListesi = null;
        while ($satir = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($kolonListesi === null) {
                $kolonListesi = implode(', ', array_map(fn($k) => '`' . str_replace('`', '``', $k) . '`', array_keys($satir)));
            }
            $degerler = [];
            foreach ($satir as $k => $v) {
                if ($v === null) {
                    $degerler[] = 'NULL';
                } elseif (!empty($ikili[$k])) {
                    $degerler[] = $v === '' ? "''" : '0x' . bin2hex($v);
                } else {
                    $degerler[] = $pdo->quote((string)$v);
                }
            }
            $paket[] = '(' . implode(', ', $degerler) . ')';
            $toplamSatir++;
            if (count($paket) >= 200) {
                $yaz("INSERT INTO $q ($kolonListesi) VALUES\n" . implode(",\n", $paket) . ";\n");
                $paket = [];
            }
        }
        if ($paket) {
            $yaz("INSERT INTO $q ($kolonListesi) VALUES\n" . implode(",\n", $paket) . ";\n");
        }
        $stmt->closeCursor();
        $yaz("\n");
    }

    foreach ($gorunumler as $gorunum) {
        $q = '`' . str_replace('`', '``', $gorunum) . '`';
        $olustur = $pdo->query("SHOW CREATE VIEW $q")->fetch(PDO::FETCH_NUM)[1];
        $olustur = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $olustur);
        $yaz("-- Görünüm: $gorunum\nDROP VIEW IF EXISTS $q;\n$olustur;\n\n");
    }

    foreach ($pdo->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC) as $tetik) {
        $q = '`' . str_replace('`', '``', $tetik['Trigger']) . '`';
        $olustur = $pdo->query("SHOW CREATE TRIGGER $q")->fetch(PDO::FETCH_ASSOC)['SQL Original Statement'];
        $olustur = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/', '', $olustur);
        $yaz("-- Tetikleyici: {$tetik['Trigger']}\nDROP TRIGGER IF EXISTS $q;\nDELIMITER $$\n$olustur$$\nDELIMITER ;\n\n");
    }

    $yaz("SET FOREIGN_KEY_CHECKS = 1;\n");
    gzclose($gz);
    return ['tablo' => count($tablolar), 'satir' => $toplamSatir];
}

/**
 * Yedek alır, R2 yedek kovasına yükler ve saklama süresi dolan yedekleri siler.
 * @return array{anahtar:string, boyut:int, tablo:int, satir:int, silinen:int}
 */
function veritabaniYedekle(PDO $pdo, ?int $saklamaGun = null): array
{
    if (!r2YedekEtkin()) {
        throw new RuntimeException('R2 yedekleme yapılandırılmamış (R2_BACKUP_BUCKET ve R2 erişim anahtarları gerekli).');
    }
    $saklamaGun = $saklamaGun ?? max(1, (int)(getenv('R2_YEDEK_SAKLAMA_GUN') ?: 30));
    $istemci = R2Istemci::ortamdan();
    $kova = getenv('R2_BACKUP_BUCKET');

    $gecici = tempnam(sys_get_temp_dir(), 'yedek');
    try {
        $bilgi = veritabaniDokumuAl($pdo, $gecici);
        $vt = preg_replace('/[^A-Za-z0-9_-]/', '_', (string)$pdo->query('SELECT DATABASE()')->fetchColumn());
        $anahtar = YEDEK_ON_EK . date('Y/m/') . $vt . '_' . date('Y-m-d_His') . '.sql.gz';
        $istemci->yukle($kova, $anahtar, $gecici, 'application/gzip', true);
        $boyut = filesize($gecici);
    } finally {
        @unlink($gecici);
    }

    // Saklama süresi dolan yedekleri sil (en yeni yedek her durumda kalır)
    $silinen = 0;
    $sinir = time() - $saklamaGun * 86400;
    foreach ($istemci->listele($kova, YEDEK_ON_EK) as $nesne) {
        if ($nesne['anahtar'] !== $anahtar && strtotime($nesne['tarih']) <= $sinir) {
            $istemci->sil($kova, $nesne['anahtar']);
            $silinen++;
        }
    }

    return ['anahtar' => $anahtar, 'boyut' => $boyut] + $bilgi + ['silinen' => $silinen];
}

/** @return array<int, array{anahtar:string, boyut:int, tarih:string}> en yeni önce */
function yedekleriListele(): array
{
    $liste = R2Istemci::ortamdan()->listele(getenv('R2_BACKUP_BUCKET'), YEDEK_ON_EK);
    usort($liste, fn($a, $b) => strcmp($b['anahtar'], $a['anahtar']));
    return $liste;
}

function yedekAnahtariGecerli(string $anahtar): bool
{
    return (bool)preg_match('#^' . preg_quote(YEDEK_ON_EK, '#') . '\d{4}/\d{2}/[A-Za-z0-9_-]+_\d{4}-\d{2}-\d{2}_\d{6}\.sql\.gz$#', $anahtar);
}

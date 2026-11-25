<?php
// cron/veritabani_yedekle.php
// Veritabanı yedeğini alıp Cloudflare R2'ye yükler. Zamanlanmış görev olarak çalıştırın, ör. Coolify
// "Scheduled Tasks": komut `php /var/www/html/cron/veritabani_yedekle.php`, sıklık `0 3 * * *` (her gece 03:00).

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/yedek.php';

try {
    $sonuc = veritabaniYedekle($pdo);
    printf(
        "Yedek alındı: %s (%s KB, %d tablo, %d satır). Süresi dolan %d yedek silindi.\n",
        $sonuc['anahtar'], number_format($sonuc['boyut'] / 1024, 1), $sonuc['tablo'], $sonuc['satir'], $sonuc['silinen']
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Yedekleme başarısız: ' . $e->getMessage() . "\n");
    exit(1);
}

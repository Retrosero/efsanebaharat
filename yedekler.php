<?php
// yedekler.php - Veritabanı yedekleri (Cloudflare R2). Yalnızca yönetici.
require_once 'includes/db.php';
require_once 'includes/yedek.php';
yoneticiGerekli();

$etkin = r2YedekEtkin();

// Süreli bağlantıyla indir
if ($etkin && isset($_GET['indir'])) {
    $anahtar = (string)$_GET['indir'];
    if (!yedekAnahtariGecerli($anahtar)) {
        http_response_code(400);
        exit('Geçersiz yedek');
    }
    header('Location: ' . R2Istemci::ortamdan()->sureliBaglanti(getenv('R2_BACKUP_BUCKET'), $anahtar, 300));
    exit;
}

// Şimdi yedek al (POST + CSRF kaynak kontrolü db.php'de)
if ($etkin && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'yedek_al') {
    @set_time_limit(600);
    try {
        $sonuc = veritabaniYedekle($pdo);
        $_SESSION['basari_mesaji'] = sprintf('Yedek alındı: %s (%s KB, %d tablo, %d satır).', basename($sonuc['anahtar']), number_format($sonuc['boyut'] / 1024, 1, ',', '.'), $sonuc['tablo'], $sonuc['satir']);
    } catch (Throwable $e) {
        error_log('Yedekleme hatası: ' . $e->getMessage());
        $_SESSION['hata_mesaji'] = 'Yedekleme başarısız: ' . $e->getMessage();
    }
    header('Location: yedekler.php');
    exit;
}

$yedekler = [];
$listeHatasi = null;
if ($etkin) {
    try {
        $yedekler = yedekleriListele();
    } catch (Throwable $e) {
        $listeHatasi = $e->getMessage();
    }
}

include 'includes/header.php';
?>
<div class="p-4 max-w-4xl">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-xl font-semibold">Veritabanı Yedekleri</h1>
    <?php if ($etkin): ?>
    <form method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').innerText = 'Yedek alınıyor...';">
      <input type="hidden" name="islem" value="yedek_al">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <button class="flex items-center px-4 py-2 bg-primary text-white rounded-button text-sm hover:opacity-90">
        <i class="ri-database-2-line mr-2"></i> Şimdi Yedek Al
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php foreach (['basari_mesaji' => 'green', 'hata_mesaji' => 'red'] as $anahtar => $renk): ?>
    <?php if (!empty($_SESSION[$anahtar])): ?>
      <div class="mb-4 p-3 rounded bg-<?= $renk ?>-50 text-<?= $renk ?>-700 text-sm"><?= e($_SESSION[$anahtar]) ?></div>
      <?php unset($_SESSION[$anahtar]); ?>
    <?php endif; ?>
  <?php endforeach; ?>

  <?php if (!$etkin): ?>
    <div class="p-4 rounded bg-yellow-50 text-yellow-800 text-sm">
      Cloudflare R2 yedekleme yapılandırılmamış. Sunucuda şu ortam değişkenlerini tanımlayın:
      <code>R2_ACCOUNT_ID</code>, <code>R2_ACCESS_KEY_ID</code>, <code>R2_SECRET_ACCESS_KEY</code>, <code>R2_BACKUP_BUCKET</code>.
    </div>
  <?php elseif ($listeHatasi): ?>
    <div class="p-4 rounded bg-red-50 text-red-700 text-sm">Yedekler listelenemedi: <?= e($listeHatasi) ?></div>
  <?php else: ?>
    <p class="text-sm text-gray-500 mb-3">
      Yedekler gizli <strong><?= e(getenv('R2_BACKUP_BUCKET')) ?></strong> kovasında tutulur;
      <?= (int)(getenv('R2_YEDEK_SAKLAMA_GUN') ?: 30) ?> günden eski yedekler otomatik silinir.
    </p>
    <div class="bg-white border rounded overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-gray-600">
          <tr><th class="text-left p-3">Yedek</th><th class="text-left p-3">Tarih</th><th class="text-right p-3">Boyut</th><th class="p-3"></th></tr>
        </thead>
        <tbody>
          <?php if (!$yedekler): ?>
            <tr><td colspan="4" class="p-4 text-center text-gray-500">Henüz yedek yok.</td></tr>
          <?php endif; ?>
          <?php foreach ($yedekler as $y): ?>
            <tr class="border-t">
              <td class="p-3 font-mono text-xs"><?= e(basename($y['anahtar'])) ?></td>
              <td class="p-3"><?= e(date('d.m.Y H:i', strtotime($y['tarih']))) ?></td>
              <td class="p-3 text-right"><?= number_format($y['boyut'] / 1024, 1, ',', '.') ?> KB</td>
              <td class="p-3 text-right">
                <?php if (yedekAnahtariGecerli($y['anahtar'])): ?>
                  <a class="text-primary hover:underline" href="yedekler.php?indir=<?= urlencode($y['anahtar']) ?>"><i class="ri-download-line"></i> İndir</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php include 'includes/footer.php'; ?>

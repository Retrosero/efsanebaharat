# Ölçüm Birimi Desteği

Bu güncelleme, ürünlerin farklı ölçüm birimleriyle (adet, kilogram, gram) satılabilmesini sağlar. Özellikle kg ile alınıp gram ile satılabilen ürünler için uygun bir çözüm sunar.

## Veritabanı Değişiklikleri

Aşağıdaki tablolara yeni sütunlar eklenmiştir:

1. **urunler** tablosu:
   - `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet'

2. **fatura_detaylari** tablosu:
   - `miktar` sütunu int(11)'den decimal(10,3)'e dönüştürüldü
   - `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet'

3. **stok_hareketleri** tablosu:
   - `miktar` sütunu int(11)'den decimal(10,3)'e dönüştürüldü
   - `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet'

## Mevcut Veritabanını Güncelleme

Mevcut veritabanını güncellemek için `olcum_birimi_guncelleme.sql` dosyasını çalıştırın:

```sql
-- Ürünler tablosuna olcum_birimi sütunu ekle
ALTER TABLE `urunler` ADD COLUMN `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet' AFTER `birim`;

-- Fatura detayları tablosundaki miktar sütununu decimal olarak değiştir ve olcum_birimi sütunu ekle
ALTER TABLE `fatura_detaylari` MODIFY COLUMN `miktar` decimal(10,3) NOT NULL;
ALTER TABLE `fatura_detaylari` ADD COLUMN `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet' AFTER `miktar`;

-- Stok hareketleri tablosundaki miktar sütununu decimal olarak değiştir ve olcum_birimi sütunu ekle
ALTER TABLE `stok_hareketleri` MODIFY COLUMN `miktar` decimal(10,3) NOT NULL;
ALTER TABLE `stok_hareketleri` ADD COLUMN `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet' AFTER `miktar`;

-- Mevcut verileri güncelle
UPDATE `urunler` SET `olcum_birimi` = 'adet';
UPDATE `fatura_detaylari` SET `olcum_birimi` = 'adet';
UPDATE `stok_hareketleri` SET `olcum_birimi` = 'adet';
```

## Yapılan Değişiklikler

### 1. Ürün Ekleme Sayfası (urun_ekle.php)

- Ölçüm birimi seçimi için yeni bir alan eklendi
- Ürün kaydederken ölçüm birimi bilgisi de kaydediliyor

### 2. Satış Sayfası (satis.php)

- Ürün sepete eklenirken ölçüm birimine göre farklı arayüz gösteriliyor
- Adet için normal sayı girişi, kg/gr için ondalıklı sayı girişi yapılabiliyor
- Kg ve gr arasında dönüşüm yapılabiliyor
- Sepette ürünün ölçüm birimi gösteriliyor

### 3. Satış Kaydetme (satis_kaydet.php)

- Ölçüm birimi bilgisi işleniyor
- Birim dönüşümleri otomatik yapılıyor (gr -> kg veya kg -> gr)
- Stok hareketleri ve fatura detaylarına ölçüm birimi kaydediliyor

## Kullanım

1. Yeni ürün eklerken "Ölçüm Birimi" alanından ürünün satış şeklini seçin (adet, kg, gr)
2. Satış ekranında, ürün sepete eklenirken ölçüm birimine göre uygun giriş alanı gösterilecektir
3. Kg ile alınan ürünleri gram olarak satmak için, satış ekranında birim seçimini "gr" olarak değiştirin

Bu güncelleme sayesinde, özellikle baharat gibi ürünleri kg ile alıp gram ile satabilirsiniz. 
## Testler

```bash
# JS (birim dönüşümleri + satış ekranı kg/gr davranışı)
npm test

# PHP birim + entegrasyon testleri (MariaDB + uygulama Docker'da ayağa kalkar)
docker compose -p efsanetest -f docker-compose.test.yml up --build --abort-on-container-exit --exit-code-from tests
docker compose -p efsanetest -f docker-compose.test.yml down -v
```

## Cloudflare R2: resimler ve veritabanı yedekleri

1. Cloudflare panelinde iki kova oluşturun:
   - `efsane-resimler`: **Settings > Public access** açın (r2.dev adresi veya özel alan adı).
   - `efsane-yedekler`: herkese açık erişim **kapalı** kalsın (yedeklerde müşteri verisi var).
2. **R2 > Manage R2 API Tokens** ile iki kovaya "Object Read & Write" yetkili bir token oluşturun.
3. Coolify'da uygulamanın ortam değişkenlerine `.env.example` içindeki `R2_*` değerlerini girin ve yeniden dağıtın.
   Bundan sonra yüklenen ürün resimleri doğrudan R2'ye gider.
4. Mevcut resimleri taşıyın (önce `--kuru` ile kontrol edin):
   ```bash
   php cron/resimleri_r2ye_tasi.php --kuru
   php cron/resimleri_r2ye_tasi.php --kaynak-url=https://panel.efsanebaharat.com
   ```
   `--kaynak-url`, sunucuda bulunmayan dosyaları bu siteden indirip taşır. Tekrar çalıştırmak güvenlidir.
5. Otomatik yedek: Coolify > uygulama > **Scheduled Tasks**: komut
   `php /var/www/html/cron/veritabani_yedekle.php`, sıklık `0 3 * * *` (her gece 03:00).
   Yedekler panelde **Yönetim > Yedekler** sayfasından listelenir, elle alınır ve indirilir.
   Geri yükleme: `gunzip -c yedek.sql.gz | mysql -u KULLANICI -p VERITABANI`

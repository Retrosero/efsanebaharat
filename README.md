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
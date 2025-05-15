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
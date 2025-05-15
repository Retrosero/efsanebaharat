-- urunler tablosuna stok_kg alanı ekle
ALTER TABLE `urunler` ADD COLUMN `stok_kg` DECIMAL(10,3) NOT NULL DEFAULT 0.000 AFTER `stok_miktari`;

-- Mevcut stok verilerini stok_kg alanına kopyala
-- Kilogram cinsinden olanlar doğrudan aktarılacak
UPDATE `urunler` SET `stok_kg` = `stok_miktari` WHERE `olcum_birimi` = 'kg';

-- Gram cinsinden olan ürünleri kg'a çevirerek aktar (1000 gr = 1 kg)
UPDATE `urunler` SET `stok_kg` = `stok_miktari` / 1000 WHERE `olcum_birimi` = 'gr';

-- Adet cinsinden olanlar için stok_kg alanı 0 olarak kalacak
-- Zaten varsayılan değer 0.000 olarak ayarlandı 
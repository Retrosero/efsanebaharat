-- odeme_tahsilat tablosunu güncelleme
ALTER TABLE `odeme_tahsilat` MODIFY COLUMN `islem_turu` enum('tahsilat','odeme','tediye') NOT NULL;

-- onay_durumu ve onayli alanlarını ekleme (eğer yoksa)
ALTER TABLE `odeme_tahsilat` ADD COLUMN IF NOT EXISTS `onay_durumu` enum('bekliyor', 'onaylandi', 'reddedildi') NOT NULL DEFAULT 'bekliyor';
ALTER TABLE `odeme_tahsilat` ADD COLUMN IF NOT EXISTS `onayli` tinyint(1) NOT NULL DEFAULT 0; 
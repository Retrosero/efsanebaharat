-- Onay işlemleri tablosu
CREATE TABLE `onay_islemleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `islem_tipi` enum('satis','alis','tahsilat','odeme') NOT NULL,
  `islem_id` int(11) NOT NULL,
  `referans_no` varchar(50) DEFAULT NULL,
  `musteri_id` int(11) DEFAULT NULL,
  `tedarikci_id` int(11) DEFAULT NULL,
  `tutar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `durum` enum('bekliyor','onaylandi','reddedildi') NOT NULL DEFAULT 'bekliyor',
  `ekleyen_id` int(11) NOT NULL,
  `onaylayan_id` int(11) DEFAULT NULL,
  `onay_tarihi` datetime DEFAULT NULL,
  `onay_notu` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `islem_tipi` (`islem_tipi`),
  KEY `islem_id` (`islem_id`),
  KEY `musteri_id` (`musteri_id`),
  KEY `tedarikci_id` (`tedarikci_id`),
  KEY `durum` (`durum`),
  KEY `ekleyen_id` (`ekleyen_id`),
  KEY `onaylayan_id` (`onaylayan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Faturalar tablosuna onay durumu ekle
ALTER TABLE `faturalar` 
ADD COLUMN `onay_durumu` enum('bekliyor','onaylandi','reddedildi') NOT NULL DEFAULT 'bekliyor' AFTER `iptal`,
ADD COLUMN `onayli` tinyint(1) NOT NULL DEFAULT 0 AFTER `onay_durumu`;

-- Ödeme ve tahsilat tablosuna onay durumu ekle
ALTER TABLE `odeme_tahsilat` 
ADD COLUMN `onay_durumu` enum('bekliyor','onaylandi','reddedildi') NOT NULL DEFAULT 'bekliyor' AFTER `kullanici_id`,
ADD COLUMN `onayli` tinyint(1) NOT NULL DEFAULT 0 AFTER `onay_durumu`;

-- Trigger eklemesi - Fatura onaylandığında onay durumunu güncelle
DELIMITER //
CREATE TRIGGER after_onay_update
AFTER UPDATE ON `onay_islemleri`
FOR EACH ROW
BEGIN
    IF NEW.durum = 'onaylandi' AND OLD.durum = 'bekliyor' THEN
        IF NEW.islem_tipi = 'satis' OR NEW.islem_tipi = 'alis' THEN
            UPDATE faturalar SET onay_durumu = 'onaylandi', onayli = 1 WHERE id = NEW.islem_id;
        ELSEIF NEW.islem_tipi = 'tahsilat' OR NEW.islem_tipi = 'odeme' THEN
            UPDATE odeme_tahsilat SET onay_durumu = 'onaylandi', onayli = 1 WHERE id = NEW.islem_id;
        END IF;
    ELSEIF NEW.durum = 'reddedildi' AND OLD.durum = 'bekliyor' THEN
        IF NEW.islem_tipi = 'satis' OR NEW.islem_tipi = 'alis' THEN
            UPDATE faturalar SET onay_durumu = 'reddedildi', onayli = 0 WHERE id = NEW.islem_id;
        ELSEIF NEW.islem_tipi = 'tahsilat' OR NEW.islem_tipi = 'odeme' THEN
            UPDATE odeme_tahsilat SET onay_durumu = 'reddedildi', onayli = 0 WHERE id = NEW.islem_id;
        END IF;
    END IF;
END; //
DELIMITER ; 
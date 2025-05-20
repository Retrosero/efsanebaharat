-- odeme_detay tablosu oluşturma
CREATE TABLE IF NOT EXISTS `odeme_detay` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `odeme_id` int(11) NOT NULL,
  `banka_id` int(11) DEFAULT NULL,
  `cek_senet_no` varchar(50) DEFAULT NULL,
  `vade_tarihi` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `odeme_id` (`odeme_id`),
  KEY `banka_id` (`banka_id`),
  CONSTRAINT `odeme_detay_ibfk_1` FOREIGN KEY (`odeme_id`) REFERENCES `odeme_tahsilat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `odeme_detay_ibfk_2` FOREIGN KEY (`banka_id`) REFERENCES `banka_listesi` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci; 
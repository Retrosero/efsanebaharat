-- Döviz para birimi alanını faturalar tablosuna ekleme
ALTER TABLE faturalar 
ADD COLUMN para_birimi ENUM('TRY', 'USD', 'EUR', 'GBP') NOT NULL DEFAULT 'TRY' 
AFTER fatura_turu;

-- Döviz kuru alanını faturalar tablosuna ekleme
ALTER TABLE faturalar 
ADD COLUMN doviz_kuru DECIMAL(10,4) NULL AFTER para_birimi;

-- Müşteriler tablosuna döviz bakiye alanları ekleme
ALTER TABLE musteriler 
ADD COLUMN usd_bakiye DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER cari_bakiye,
ADD COLUMN eur_bakiye DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER usd_bakiye,
ADD COLUMN gbp_bakiye DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER eur_bakiye;

-- Döviz kurları için yeni tablo oluşturma
CREATE TABLE IF NOT EXISTS doviz_kurlari (
  id INT(11) NOT NULL AUTO_INCREMENT,
  para_birimi ENUM('USD', 'EUR', 'GBP') NOT NULL,
  alis_kuru DECIMAL(10,4) NOT NULL,
  satis_kuru DECIMAL(10,4) NOT NULL,
  guncelleme_tarihi TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY para_birimi (para_birimi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Varsayılan döviz kurları ekleme
INSERT INTO doviz_kurlari (para_birimi, alis_kuru, satis_kuru) VALUES
('USD', 30.5000, 30.7000),
('EUR', 32.4000, 32.6000),
('GBP', 38.1000, 38.3000)
ON DUPLICATE KEY UPDATE
alis_kuru = VALUES(alis_kuru),
satis_kuru = VALUES(satis_kuru),
guncelleme_tarihi = CURRENT_TIMESTAMP; 
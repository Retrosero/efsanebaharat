-- Veritabanını oluştur (varsa sil)
DROP DATABASE IF EXISTS efsaneba_uygulama;
CREATE DATABASE efsaneba_uygulama DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE efsaneba_uygulama;

-- Kullanıcılar tablosu
CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kullanici_adi` varchar(100) NOT NULL,
  `eposta` varchar(255) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `rol_id` int(11) NOT NULL DEFAULT 2,
  `eposta_bildirim` tinyint(1) NOT NULL DEFAULT 1,
  `sms_bildirim` tinyint(1) NOT NULL DEFAULT 1,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `eposta` (`eposta`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Kullanıcı rolleri tablosu
CREATE TABLE `roller` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol_adi` varchar(50) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Sayfa yetkileri tablosu
CREATE TABLE `sayfa_yetkileri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol_id` int(11) NOT NULL,
  `sayfa_adi` varchar(100) NOT NULL,
  `goruntuleme` tinyint(1) NOT NULL DEFAULT 0,
  `ekleme` tinyint(1) NOT NULL DEFAULT 0,
  `duzenleme` tinyint(1) NOT NULL DEFAULT 0,
  `silme` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `rol_id` (`rol_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Müşteri Tipleri Tablosu
CREATE TABLE IF NOT EXISTS `musteri_tipleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tip_adi` varchar(100) NOT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Müşteriler tablosu
CREATE TABLE `musteriler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `musteri_kodu` varchar(20) DEFAULT NULL,
  `ad` varchar(100) NOT NULL,
  `soyad` varchar(100) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `vergi_no` varchar(50) DEFAULT NULL,
  `tip_id` int(11) DEFAULT NULL,
  `cari_bakiye` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) DEFAULT NULL,
  `vergi_dairesi` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `tip_id` (`tip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Tedarikçiler tablosu
CREATE TABLE `tedarikciler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tedarikci_kodu` varchar(20) DEFAULT NULL,
  `firma_adi` varchar(255) NOT NULL,
  `yetkili_adi` varchar(100) DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `vergi_no` varchar(50) DEFAULT NULL,
  `cari_bakiye` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `email` varchar(255) DEFAULT NULL,
  `vergi_dairesi` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Markalar tablosu
CREATE TABLE `markalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `marka_adi` varchar(100) NOT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Kategoriler tablosu
CREATE TABLE `kategoriler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_adi` varchar(100) NOT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Alt Kategoriler tablosu
CREATE TABLE `alt_kategoriler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) NOT NULL,
  `alt_kategori_adi` varchar(100) NOT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Ürünler tablosu
CREATE TABLE `urunler` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urun_adi` varchar(255) NOT NULL,
  `urun_kodu` varchar(50) DEFAULT NULL,
  `barkod` varchar(50) DEFAULT NULL,
  `kategori_id` int(11) DEFAULT NULL,
  `alt_kategori_id` int(11) DEFAULT NULL,
  `marka_id` int(11) DEFAULT NULL,
  `aciklama` text DEFAULT NULL,
  `alis_fiyati` decimal(10,2) NOT NULL DEFAULT 0.00,
  `birim_fiyat` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kdv_orani` decimal(5,2) NOT NULL DEFAULT 18.00,
  `stok_miktari` decimal(10,3) NOT NULL DEFAULT 0.000,
  `minimum_stok` int(11) NOT NULL DEFAULT 5,
  `birim` varchar(20) DEFAULT 'Adet',
  `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet',
  `resim_url_1` varchar(255) DEFAULT NULL,
  `resim_url_2` varchar(255) DEFAULT NULL,
  `resim_url_3` varchar(255) DEFAULT NULL,
  `resim_url_4` varchar(255) DEFAULT NULL,
  `resim_url_5` varchar(255) DEFAULT NULL,
  `resim_url_6` varchar(255) DEFAULT NULL,
  `resim_url_7` varchar(255) DEFAULT NULL,
  `resim_url_8` varchar(255) DEFAULT NULL,
  `resim_url_9` varchar(255) DEFAULT NULL,
  `resim_url_10` varchar(255) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kategori_id` (`kategori_id`),
  KEY `alt_kategori_id` (`alt_kategori_id`),
  KEY `marka_id` (`marka_id`),
  KEY `urun_kodu` (`urun_kodu`),
  KEY `barkod` (`barkod`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Stok hareketleri tablosu
CREATE TABLE `stok_hareketleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `urun_id` int(11) NOT NULL,
  `hareket_tipi` enum('giris','cikis') NOT NULL,
  `miktar` decimal(10,3) NOT NULL,
  `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet',
  `aciklama` varchar(255) DEFAULT NULL,
  `belge_no` varchar(50) DEFAULT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `kullanici_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `urun_id` (`urun_id`),
  KEY `fatura_id` (`fatura_id`),
  KEY `kullanici_id` (`kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Faturalar tablosu
CREATE TABLE `faturalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fatura_no` varchar(50) DEFAULT NULL,
  `fatura_tarihi` date NOT NULL,
  `vade_tarihi` date DEFAULT NULL,
  `fatura_turu` enum('satis','alis') NOT NULL,
  `musteri_id` int(11) DEFAULT NULL,
  `tedarikci_id` int(11) DEFAULT NULL,
  `toplam_tutar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `indirim_tutari` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vergi_tutari` decimal(10,2) NOT NULL DEFAULT 0.00,
  `genel_toplam` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aciklama` text DEFAULT NULL,
  `odeme_durumu` enum('odenmedi','kismen_odendi','odendi') NOT NULL DEFAULT 'odenmedi',
  `odenen_tutar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kalan_tutar` decimal(10,2) NOT NULL DEFAULT 0.00,
  `kullanici_id` int(11) DEFAULT NULL,
  `iptal` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `musteri_id` (`musteri_id`),
  KEY `tedarikci_id` (`tedarikci_id`),
  KEY `kullanici_id` (`kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Fatura detayları tablosu
CREATE TABLE `fatura_detaylari` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fatura_id` int(11) NOT NULL,
  `urun_id` int(11) NOT NULL,
  `urun_adi` varchar(255) NOT NULL,
  `miktar` decimal(10,3) NOT NULL,
  `olcum_birimi` enum('adet','kg','gr') NOT NULL DEFAULT 'adet',
  `birim_fiyat` decimal(10,2) NOT NULL,
  `toplam_fiyat` decimal(10,2) NOT NULL,
  `kdv_orani` decimal(5,2) NOT NULL DEFAULT 18.00,
  `kdv_tutari` decimal(10,2) NOT NULL,
  `indirim_orani` decimal(5,2) NOT NULL DEFAULT 0.00,
  `indirim_tutari` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_tutar` decimal(10,2) NOT NULL,
  `urun_notu` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fatura_id` (`fatura_id`),
  KEY `urun_id` (`urun_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Ödeme ve tahsilat tablosu
CREATE TABLE `odeme_tahsilat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `islem_turu` enum('tahsilat','odeme') NOT NULL,
  `musteri_id` int(11) DEFAULT NULL,
  `tedarikci_id` int(11) DEFAULT NULL,
  `fatura_id` int(11) DEFAULT NULL,
  `tutar` decimal(10,2) NOT NULL,
  `odeme_turu` enum('nakit','kredi_karti','havale','cek','senet','diger') NOT NULL DEFAULT 'nakit',
  `aciklama` text DEFAULT NULL,
  `islem_tarihi` date NOT NULL,
  `banka_id` int(11) DEFAULT NULL,
  `evrak_no` varchar(50) DEFAULT NULL,
  `kullanici_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `musteri_id` (`musteri_id`),
  KEY `tedarikci_id` (`tedarikci_id`),
  KEY `fatura_id` (`fatura_id`),
  KEY `banka_id` (`banka_id`),
  KEY `kullanici_id` (`kullanici_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Banka listesi tablosu
CREATE TABLE `banka_listesi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `banka_adi` varchar(100) NOT NULL,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Ayarlar tablosu
CREATE TABLE `ayarlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `firma_adi` varchar(255) DEFAULT NULL,
  `firma_telefon` varchar(20) DEFAULT NULL,
  `firma_eposta` varchar(255) DEFAULT NULL,
  `firma_adres` text DEFAULT NULL,
  `firma_vergi_no` varchar(50) DEFAULT NULL,
  `firma_vergi_dairesi` varchar(100) DEFAULT NULL,
  `site_baslik` varchar(255) DEFAULT NULL,
  `site_aciklama` text DEFAULT NULL,
  `site_logo` varchar(255) DEFAULT NULL,
  `varsayilan_kdv` decimal(5,2) NOT NULL DEFAULT 18.00,
  `para_birimi` varchar(10) NOT NULL DEFAULT '₺',
  `tema_rengi` varchar(20) DEFAULT 'primary',
  `karanlik_mod` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Sayfalar tablosu
CREATE TABLE `sayfalar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sayfa_adi` varchar(100) NOT NULL,
  `sayfa_url` varchar(100) NOT NULL,
  `aciklama` text DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sayfa_url` (`sayfa_url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Rol Sayfa İzinleri tablosu
CREATE TABLE `rol_sayfa_izinleri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rol_id` int(11) NOT NULL,
  `sayfa_id` int(11) NOT NULL,
  `izin` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rol_sayfa` (`rol_id`,`sayfa_id`),
  KEY `sayfa_id` (`sayfa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- Varsayılan veri ekleme

-- Roller için varsayılan veriler
INSERT INTO `roller` (`rol_adi`, `aciklama`) VALUES
('Yönetici', 'Tüm sisteme erişim yetkisine sahip'),
('Çalışan', 'Sınırlı yetkilere sahip standart kullanıcı');

-- Admin kullanıcısı ekleme (şifre: admin123)
INSERT INTO `kullanicilar` (`kullanici_adi`, `eposta`, `sifre`, `rol_id`) VALUES
('Admin', 'admin@efsanebaharat.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);

-- Müşteri tipleri için varsayılan değerler
INSERT INTO `musteri_tipleri` (`tip_adi`, `durum`) VALUES
('Standart Müşteri', 1),
('VIP Müşteri', 1),
('Toptan Müşteri', 1);

-- Banka için varsayılan değerler
INSERT INTO `banka_listesi` (`banka_adi`) VALUES
('Ziraat Bankası'),
('İş Bankası'),
('Garanti Bankası'),
('Yapı Kredi Bankası'),
('Akbank');

-- Kategoriler için varsayılan değerler
INSERT INTO `kategoriler` (`kategori_adi`) VALUES
('Baharatlar'),
('Kuruyemişler'),
('Çaylar'),
('Şifalı Bitkiler');

-- Alt kategoriler için varsayılan değerler
INSERT INTO `alt_kategoriler` (`kategori_id`, `alt_kategori_adi`) VALUES
(1, 'Toz Baharatlar'),
(1, 'Karışık Baharatlar'),
(2, 'Çiğ Kuruyemişler'),
(2, 'Kavrulmuş Kuruyemişler'),
(3, 'Siyah Çaylar'),
(3, 'Yeşil Çaylar'),
(4, 'Kurutulmuş Bitkiler');

-- Markalar için varsayılan değerler
INSERT INTO `markalar` (`marka_adi`) VALUES
('Efsane Baharat'),
('Doğal Lezzetler'),
('Anadolu Baharat'),
('Öz Baharat');

-- Ayarlar için varsayılan değerler
INSERT INTO `ayarlar` (`firma_adi`, `firma_telefon`, `firma_eposta`, `firma_adres`, `site_baslik`, `varsayilan_kdv`, `para_birimi`) VALUES
('Efsane Baharat', '+90 555 123 4567', 'info@efsanebaharat.com', 'Baharat Sokak No:1 İstanbul', 'Efsane Baharat - Yönetim Paneli', 18.00, '₺');

-- Sayfalar için varsayılan değerler
INSERT INTO `sayfalar` (`sayfa_adi`, `sayfa_url`, `aciklama`) VALUES
('Ana Sayfa', 'index.php', 'Ana sayfa'),
('Satış', 'satis.php', 'Satış ekranı'),
('Alış', 'alis.php', 'Alış ekranı'),
('Alış Faturaları', 'alis_faturalari.php', 'Alış faturaları listesi'),
('Tahsilat', 'tahsilat.php', 'Tahsilat işlemleri'),
('Ürünler', 'urunler.php', 'Ürün yönetimi'),
('Müşteriler', 'musteriler.php', 'Müşteri yönetimi'),
('Onay Merkezi', 'onay_merkezi.php', 'Onay bekleyen işlemler'),
('Kullanıcılar', 'kullanicilar.php', 'Kullanıcı yönetimi'),
('Roller', 'roller.php', 'Rol yönetimi'),
('Raporlar', 'raporlar.php', 'Raporlar'),
('Ayarlar', 'ayarlar.php', 'Sistem ayarları');

-- Foreign Key Constraints
ALTER TABLE `sayfa_yetkileri` ADD CONSTRAINT `sayfa_yetkileri_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roller` (`id`) ON DELETE CASCADE;
ALTER TABLE `musteriler` ADD CONSTRAINT `musteriler_ibfk_1` FOREIGN KEY (`tip_id`) REFERENCES `musteri_tipleri` (`id`) ON DELETE SET NULL;
ALTER TABLE `alt_kategoriler` ADD CONSTRAINT `alt_kategoriler_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler` (`id`) ON DELETE CASCADE;
ALTER TABLE `urunler` ADD CONSTRAINT `urunler_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `kategoriler` (`id`) ON DELETE SET NULL;
ALTER TABLE `urunler` ADD CONSTRAINT `urunler_ibfk_2` FOREIGN KEY (`alt_kategori_id`) REFERENCES `alt_kategoriler` (`id`) ON DELETE SET NULL;
ALTER TABLE `urunler` ADD CONSTRAINT `urunler_ibfk_3` FOREIGN KEY (`marka_id`) REFERENCES `markalar` (`id`) ON DELETE SET NULL;
ALTER TABLE `stok_hareketleri` ADD CONSTRAINT `stok_hareketleri_ibfk_1` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE CASCADE;
ALTER TABLE `stok_hareketleri` ADD CONSTRAINT `stok_hareketleri_ibfk_2` FOREIGN KEY (`fatura_id`) REFERENCES `faturalar` (`id`) ON DELETE SET NULL;
ALTER TABLE `stok_hareketleri` ADD CONSTRAINT `stok_hareketleri_ibfk_3` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL;
ALTER TABLE `faturalar` ADD CONSTRAINT `faturalar_ibfk_1` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE SET NULL;
ALTER TABLE `faturalar` ADD CONSTRAINT `faturalar_ibfk_2` FOREIGN KEY (`tedarikci_id`) REFERENCES `tedarikciler` (`id`) ON DELETE SET NULL;
ALTER TABLE `faturalar` ADD CONSTRAINT `faturalar_ibfk_3` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL;
ALTER TABLE `fatura_detaylari` ADD CONSTRAINT `fatura_detaylari_ibfk_1` FOREIGN KEY (`fatura_id`) REFERENCES `faturalar` (`id`) ON DELETE CASCADE;
ALTER TABLE `fatura_detaylari` ADD CONSTRAINT `fatura_detaylari_ibfk_2` FOREIGN KEY (`urun_id`) REFERENCES `urunler` (`id`) ON DELETE CASCADE;
ALTER TABLE `odeme_tahsilat` ADD CONSTRAINT `odeme_tahsilat_ibfk_1` FOREIGN KEY (`musteri_id`) REFERENCES `musteriler` (`id`) ON DELETE SET NULL;
ALTER TABLE `odeme_tahsilat` ADD CONSTRAINT `odeme_tahsilat_ibfk_2` FOREIGN KEY (`tedarikci_id`) REFERENCES `tedarikciler` (`id`) ON DELETE SET NULL;
ALTER TABLE `odeme_tahsilat` ADD CONSTRAINT `odeme_tahsilat_ibfk_3` FOREIGN KEY (`fatura_id`) REFERENCES `faturalar` (`id`) ON DELETE SET NULL;
ALTER TABLE `odeme_tahsilat` ADD CONSTRAINT `odeme_tahsilat_ibfk_4` FOREIGN KEY (`banka_id`) REFERENCES `banka_listesi` (`id`) ON DELETE SET NULL;
ALTER TABLE `odeme_tahsilat` ADD CONSTRAINT `odeme_tahsilat_ibfk_5` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`) ON DELETE SET NULL;
ALTER TABLE `rol_sayfa_izinleri` ADD CONSTRAINT `rol_sayfa_izinleri_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roller` (`id`) ON DELETE CASCADE;
ALTER TABLE `rol_sayfa_izinleri` ADD CONSTRAINT `rol_sayfa_izinleri_ibfk_2` FOREIGN KEY (`sayfa_id`) REFERENCES `sayfalar` (`id`) ON DELETE CASCADE;

CREATE TABLE ambalaj_tipleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(255) NOT NULL,
    aciklama TEXT NULL,
    olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

ALTER TABLE ambalaj_tipleri ADD COLUMN ambalaj_adi VARCHAR(255) NOT NULL;

ALTER TABLE urunler ADD COLUMN ambalaj_id INT NULL;
ALTER TABLE urunler MODIFY COLUMN ambalaj_id INT;
ALTER TABLE ambalaj_tipleri MODIFY COLUMN id INT;

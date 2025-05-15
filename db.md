# Veritabanı Yapısı

## Tablolar

### 1. urunler
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `urun_kodu` VARCHAR(50)
- `barkod` VARCHAR(50)
- `urun_adi` VARCHAR(255) NOT NULL
- `raf_no` VARCHAR(20)
- `ambalaj` VARCHAR(100)
- `koli_adeti` INT DEFAULT 1
- `marka_id` INT
- `ambalaj_id` INT
- `birim_fiyat` DECIMAL(10,2) NOT NULL
- `stok_miktari` INT DEFAULT 0
- `aciklama` TEXT
- `resim_url` VARCHAR(255)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- FOREIGN KEY (`marka_id`) REFERENCES `markalar`(`id`)
- FOREIGN KEY (`ambalaj_id`) REFERENCES `ambalaj_tipleri`(`id`)

### 2. markalar
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `marka_adi` VARCHAR(100) NOT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### 3. ambalaj_tipleri
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `ambalaj_adi` VARCHAR(100) NOT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### 4. musteriler
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `musteri_kodu` VARCHAR(50)
- `ad` VARCHAR(100) NOT NULL
- `soyad` VARCHAR(100)
- `vergi_no` VARCHAR(20)
- `telefon` VARCHAR(20)
- `email` VARCHAR(100)
- `adres` TEXT
- `vergi_dairesi` VARCHAR(100)
- `cari_bakiye` DECIMAL(10,2) DEFAULT 0
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

### 5. faturalar
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `fatura_no` VARCHAR(50)
- `fatura_turu` ENUM('satis', 'alis') NOT NULL
- `musteri_id` INT NOT NULL
- `toplam_tutar` DECIMAL(10,2) NOT NULL
- `iskonto_orani` DECIMAL(5,2) DEFAULT 0
- `fatura_durum` ENUM('bekliyor', 'onaylandi', 'iptal') DEFAULT 'bekliyor'
- `tarih` DATE
- `vade_tarihi` DATE
- `aciklama` TEXT
- `olusturan_kullanici_id` INT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- FOREIGN KEY (`musteri_id`) REFERENCES `musteriler`(`id`)

### 6. fatura_detaylari
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `fatura_id` INT NOT NULL
- `urun_id` INT NOT NULL
- `miktar` INT NOT NULL
- `birim_fiyat` DECIMAL(10,2) NOT NULL
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- FOREIGN KEY (`fatura_id`) REFERENCES `faturalar`(`id`)
- FOREIGN KEY (`urun_id`) REFERENCES `urunler`(`id`)

### 7. odeme_tahsilat
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `musteri_id` INT NOT NULL
- `tutar` DECIMAL(10,2) NOT NULL
- `odeme_yontemi` ENUM('nakit', 'kredi', 'havale', 'eft', 'cek', 'senet') NOT NULL
- `islem_tarihi` DATE NOT NULL
- `aciklama` TEXT
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
- FOREIGN KEY (`musteri_id`) REFERENCES `musteriler`(`id`)

### 8. kullanicilar
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `kullanici_adi` VARCHAR(50) NOT NULL UNIQUE
- `sifre` VARCHAR(255) NOT NULL
- `ad_soyad` VARCHAR(100) NOT NULL
- `email` VARCHAR(100) NOT NULL UNIQUE
- `rol` ENUM('admin', 'kullanici') DEFAULT 'kullanici'
- `aktif` TINYINT(1) DEFAULT 1
- `son_giris` DATETIME
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

## İlişkiler

1. `urunler.marka_id` -> `markalar.id`
2. `urunler.ambalaj_id` -> `ambalaj_tipleri.id`
3. `faturalar.musteri_id` -> `musteriler.id`
4. `fatura_detaylari.fatura_id` -> `faturalar.id`
5. `fatura_detaylari.urun_id` -> `urunler.id`
6. `odeme_tahsilat.musteri_id` -> `musteriler.id`

## Önemli Notlar

1. Tüm parasal değerler DECIMAL(10,2) olarak tutulur
2. Tüm tablolarda created_at ve updated_at alanları bulunur
3. Silme işlemleri için soft delete yaklaşımı kullanılabilir (deleted_at kolonu eklenebilir)
4. Fatura numaraları otomatik olarak oluşturulmalıdır
5. Stok hareketleri için trigger kullanılabilir
6. Müşteri bakiyesi için trigger kullanılabilir 
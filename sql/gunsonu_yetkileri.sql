-- Gün sonu raporu sayfasını ekle
INSERT INTO sayfalar (sayfa_adi, aciklama, aktif) 
VALUES ('gunsonu', 'Gün Sonu Raporu', 1)
ON DUPLICATE KEY UPDATE aktif = 1;

-- Yönetici rolü için yetkileri ekle
INSERT INTO sayfa_yetkileri (rol_id, sayfa_adi, goruntuleme, ekleme, duzenleme, silme)
SELECT 1, 'gunsonu', 1, 1, 1, 1
FROM roller WHERE rol_adi = 'Yönetici'
ON DUPLICATE KEY UPDATE 
    goruntuleme = 1,
    ekleme = 1,
    duzenleme = 1,
    silme = 1;

-- Personel rolü için yetkileri ekle
INSERT INTO sayfa_yetkileri (rol_id, sayfa_adi, goruntuleme, ekleme, duzenleme, silme)
SELECT 2, 'gunsonu', 1, 0, 0, 0
FROM roller WHERE rol_adi = 'Personel'
ON DUPLICATE KEY UPDATE 
    goruntuleme = 1,
    ekleme = 0,
    duzenleme = 0,
    silme = 0; 